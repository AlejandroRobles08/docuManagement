<?php

namespace App\Services\Forgery\Checks;

use App\Services\Forgery\Contracts\ForgeryCheck;
use App\Services\Forgery\ForgeryFinding;
use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Busca el patrón "mosaiqueado" típico de una marca de agua superpuesta.
 *
 * Casi cualquier marca de agua (un texto, un logotipo o un sello repetido)
 * se coloca en una rejilla regular para cubrir todo el documento y
 * sobrevivir a recortes -normalmente rotada en diagonal, precisamente para
 * eso-. Eso deja un pico de energía muy concreto en el espectro de
 * frecuencia 2D de la imagen (transformada de Fourier), sin importar el
 * ángulo del mosaico -algo que un análisis solo por filas o columnas no
 * puede ver, porque un patrón diagonal se "cancela" al promediarlo por eje-.
 *
 * El proceso:
 *  1. Reduce la imagen a una rejilla de análisis pequeña en escala de grises
 *     (o del canal alfa, si el PNG lo trae).
 *  2. Le quita el contenido de baja frecuencia (formas grandes, degradados
 *     de luz) con un filtro paso-alto, para quedarnos con el detalle fino
 *     -trazos de texto- donde vive una marca de agua.
 *  3. Calcula su transformada de Fourier 2D y compara cada frecuencia contra
 *     el promedio de su "anillo" (mismo radio, cualquier ángulo). El
 *     contenido natural de un documento (incluidas tablas y recuadros con
 *     bordes rectos) decae con suavidad según la distancia al origen; un
 *     patrón que se repite rompe esa suavidad con un pico muy por encima de
 *     sus vecinos del mismo anillo.
 *
 * Se buscan dos tipos de pico por separado: uno explícitamente diagonal (el
 * caso más común en la práctica) con un umbral moderado, y otro en
 * cualquier ángulo -incluido horizontal/vertical- pero exigiendo un umbral
 * mucho más alto, porque los bordes rectos de tablas y recuadros ya
 * producen por sí solos algo de energía alineada a los ejes.
 *
 * Como con el resto de heurísticas de este paquete, esto NO es una prueba
 * concluyente: solo suma puntos a la puntuación total de riesgo.
 */
class WatermarkDetectionCheck implements ForgeryCheck
{
    /**
     * Debe ser potencia de 2 (lo exige la FFT radix-2 usada abajo).
     */
    private const int ANALYSIS_SIZE = 128;

    private const int HIGH_PASS_RADIUS = 10;

    /**
     * Ignora el entorno inmediato del origen (frecuencia cero): ahí vive el
     * brillo medio y las tendencias muy lentas, no un patrón repetido.
     */
    private const int DC_GUARD = 3;

    /**
     * Una frecuencia cuenta como "diagonal" solo si tiene componente
     * vertical Y horizontal de al menos este tamaño; así se ignoran los
     * picos puramente horizontales o verticales (bordes de tablas y
     * recuadros), que se evalúan aparte con un umbral más exigente.
     */
    private const int DIAGONAL_MIN_COMPONENT = 8;

    private const float DIAGONAL_RATIO_THRESHOLD = 20.0;

    private const float ANY_ANGLE_RATIO_THRESHOLD = 200.0;

    /**
     * Piso absoluto de energía: una imagen casi plana (un degradado suave,
     * por ejemplo) puede dar una proporción alta por pura división entre
     * números diminutos, sin que haya ninguna textura real que analizar.
     */
    private const float MIN_PEAK_MAGNITUDE = 100_000.0;

    private const float ALPHA_COVERAGE_THRESHOLD = 0.08;

    public function evaluate(UploadedFile $file, array $context): ForgeryFinding
    {
        $mime = $file->getMimeType();

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            default => null,
        };

        if (! $image instanceof GdImage) {
            // PDF u otro tipo no soportado por esta heurística basada en
            // píxeles: sin señal, no penaliza.
            return ForgeryFinding::clear();
        }

        try {
            $hasAlpha = $mime === 'image/png';
            $resized = $this->resize($image, $hasAlpha);

            try {
                [$luminance, $alpha] = $this->extractChannels($resized, $hasAlpha);

                if ($alpha !== null) {
                    $finding = $this->evaluateAlphaChannel($alpha);

                    if ($finding !== null) {
                        return $finding;
                    }
                }

                return $this->evaluateLuminance($luminance) ?? ForgeryFinding::clear();
            } finally {
                imagedestroy($resized);
            }
        } finally {
            imagedestroy($image);
        }
    }

    private function resize(GdImage $image, bool $preserveAlpha): GdImage
    {
        $size = self::ANALYSIS_SIZE;
        $resized = imagecreatetruecolor($size, $size);

        if ($preserveAlpha) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));

        return $resized;
    }

    /**
     * @return array{0: list<list<float>>, 1: list<list<int>>|null}
     */
    private function extractChannels(GdImage $image, bool $hasAlpha): array
    {
        $size = self::ANALYSIS_SIZE;
        $luminance = [];
        $alpha = $hasAlpha ? [] : null;

        for ($y = 0; $y < $size; $y++) {
            $luminanceRow = [];
            $alphaRow = [];

            for ($x = 0; $x < $size; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                $luminanceRow[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;

                if ($hasAlpha) {
                    // GD guarda el alfa en 7 bits: 0 = opaco, 127 = totalmente transparente.
                    $alphaRow[] = ($rgba >> 24) & 0x7F;
                }
            }

            $luminance[] = $luminanceRow;

            if ($hasAlpha) {
                $alpha[] = $alphaRow;
            }
        }

        return [$luminance, $alpha];
    }

    /**
     * @param  list<list<int>>  $alpha
     */
    private function evaluateAlphaChannel(array $alpha): ?ForgeryFinding
    {
        $size = count($alpha);
        $semiTransparentCount = 0;
        $channel = [];

        foreach ($alpha as $row) {
            $channelRow = [];

            foreach ($row as $value) {
                $channelRow[] = (float) $value;

                if ($value > 0 && $value < 127) {
                    $semiTransparentCount++;
                }
            }

            $channel[] = $channelRow;
        }

        if ($this->hasTiledPattern($channel)) {
            return ForgeryFinding::flag(
                score: 45,
                reason: 'El canal de transparencia del PNG muestra un patrón que se repite de forma regular en toda la imagen, típico de una marca de agua mosaiqueada (por ejemplo, un texto o logotipo semitransparente repetido).',
            );
        }

        $coverage = $semiTransparentCount / ($size * $size);

        if ($coverage >= self::ALPHA_COVERAGE_THRESHOLD) {
            $percentage = round($coverage * 100);

            return ForgeryFinding::flag(
                score: 20,
                reason: "El {$percentage}% de la imagen tiene píxeles parcialmente transparentes, lo que puede indicar una capa de marca de agua superpuesta sobre el documento.",
            );
        }

        return null;
    }

    /**
     * @param  list<list<float>>  $luminance
     */
    private function evaluateLuminance(array $luminance): ?ForgeryFinding
    {
        if (! $this->hasTiledPattern($luminance)) {
            return null;
        }

        return ForgeryFinding::flag(
            score: 30,
            reason: 'Se detectó un patrón visual que se repite de forma regular en toda la imagen (en cualquier ángulo), un indicio típico de una marca de agua o sello mosaiqueado sobre el documento.',
        );
    }

    /**
     * @param  list<list<float>>  $channel
     */
    private function hasTiledPattern(array $channel): bool
    {
        $windowed = $this->applyHannWindow($this->applyHighPassFilter($channel));
        [$real, $imaginary] = $this->fft2d($windowed);

        $size = count($channel);

        /** @var list<list<float>> $magnitudes */
        $magnitudes = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $magnitudes[$y][$x] = $real[$y][$x] ** 2 + $imaginary[$y][$x] ** 2;
            }
        }

        // Agrupa cada frecuencia por su distancia (redondeada) al origen,
        // para poder comparar un pico contra el promedio de "su anillo" en
        // un solo barrido en vez de comparar cada bin contra todos los demás.
        $ringSums = [];
        $ringCounts = [];
        $ringOf = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $fy = min($y, $size - $y);
                $fx = min($x, $size - $x);
                $ring = (int) round(sqrt($fy ** 2 + $fx ** 2));
                $ringOf[$y][$x] = $ring;
                $ringSums[$ring] = ($ringSums[$ring] ?? 0.0) + $magnitudes[$y][$x];
                $ringCounts[$ring] = ($ringCounts[$ring] ?? 0) + 1;
            }
        }

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $ring = $ringOf[$y][$x];

                if ($ring < self::DC_GUARD || $ringCounts[$ring] < 5) {
                    continue;
                }

                $magnitude = $magnitudes[$y][$x];

                if ($magnitude < self::MIN_PEAK_MAGNITUDE) {
                    continue;
                }

                $ringAverage = ($ringSums[$ring] - $magnitude) / ($ringCounts[$ring] - 1);
                $ratio = $magnitude / max(1.0, $ringAverage);

                if ($ratio >= self::ANY_ANGLE_RATIO_THRESHOLD) {
                    return true;
                }

                $fy = min($y, $size - $y);
                $fx = min($x, $size - $x);

                if (
                    $fy >= self::DIAGONAL_MIN_COMPONENT
                    && $fx >= self::DIAGONAL_MIN_COMPONENT
                    && $ratio >= self::DIAGONAL_RATIO_THRESHOLD
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Filtro paso-alto 2D: resta un promedio de caja grande (calculado con
     * una tabla de área sumada, para que el radio no afecte el rendimiento)
     * y así elimina el contenido de baja frecuencia -formas grandes,
     * degradados de luz- que de otro modo dominaría el espectro y taparía la
     * señal, mucho más débil, de una marca de agua.
     *
     * @param  list<list<float>>  $channel
     * @return list<list<float>>
     */
    private function applyHighPassFilter(array $channel): array
    {
        $size = count($channel);
        $radius = self::HIGH_PASS_RADIUS;

        $integral = array_fill(0, $size + 1, array_fill(0, $size + 1, 0.0));

        for ($y = 0; $y < $size; $y++) {
            $rowSum = 0.0;

            for ($x = 0; $x < $size; $x++) {
                $rowSum += $channel[$y][$x];
                $integral[$y + 1][$x + 1] = $integral[$y][$x + 1] + $rowSum;
            }
        }

        $boxAverage = function (int $y0, int $x0, int $y1, int $x1) use ($integral): float {
            $sum = $integral[$y1 + 1][$x1 + 1] - $integral[$y0][$x1 + 1] - $integral[$y1 + 1][$x0] + $integral[$y0][$x0];

            return $sum / (($y1 - $y0 + 1) * ($x1 - $x0 + 1));
        };

        $highPassed = [];

        for ($y = 0; $y < $size; $y++) {
            $row = [];
            $y0 = max(0, $y - $radius);
            $y1 = min($size - 1, $y + $radius);

            for ($x = 0; $x < $size; $x++) {
                $x0 = max(0, $x - $radius);
                $x1 = min($size - 1, $x + $radius);

                $row[] = $channel[$y][$x] - $boxAverage($y0, $x0, $y1, $x1);
            }

            $highPassed[] = $row;
        }

        return $highPassed;
    }

    /**
     * Ventana de Hann 2D: atenúa los bordes de la imagen hacia cero antes de
     * la FFT. Sin esto, el borde discontinuo de una imagen no periódica -la
     * FFT la trata como si se repitiera infinitamente- genera energía
     * espuria repartida por el espectro, que puede confundirse con un pico
     * real.
     *
     * @param  list<list<float>>  $channel
     * @return list<list<float>>
     */
    private function applyHannWindow(array $channel): array
    {
        $size = count($channel);
        $windowed = [];

        for ($y = 0; $y < $size; $y++) {
            $wy = 0.5 * (1 - cos(2 * M_PI * $y / ($size - 1)));
            $row = [];

            for ($x = 0; $x < $size; $x++) {
                $wx = 0.5 * (1 - cos(2 * M_PI * $x / ($size - 1)));
                $row[] = $channel[$y][$x] * $wy * $wx;
            }

            $windowed[] = $row;
        }

        return $windowed;
    }

    /**
     * Transformada de Fourier 2D de una señal real, calculada como una FFT
     * 1D aplicada primero a cada fila y luego a cada columna (la FFT 2D es
     * separable).
     *
     * @param  list<list<float>>  $channel
     * @return array{0: list<list<float>>, 1: list<list<float>>}
     */
    private function fft2d(array $channel): array
    {
        $size = count($channel);
        $real = $channel;
        $imaginary = array_fill(0, $size, array_fill(0, $size, 0.0));

        for ($y = 0; $y < $size; $y++) {
            $rowReal = $real[$y];
            $rowImaginary = $imaginary[$y];
            $this->fft1d($rowReal, $rowImaginary);
            $real[$y] = $rowReal;
            $imaginary[$y] = $rowImaginary;
        }

        for ($x = 0; $x < $size; $x++) {
            $colReal = [];
            $colImaginary = [];

            for ($y = 0; $y < $size; $y++) {
                $colReal[] = $real[$y][$x];
                $colImaginary[] = $imaginary[$y][$x];
            }

            $this->fft1d($colReal, $colImaginary);

            for ($y = 0; $y < $size; $y++) {
                $real[$y][$x] = $colReal[$y];
                $imaginary[$y][$x] = $colImaginary[$y];
            }
        }

        return [$real, $imaginary];
    }

    /**
     * FFT recursiva radix-2 (Cooley-Tukey). Exige que count($real) sea
     * potencia de 2 (ver ANALYSIS_SIZE).
     *
     * @param  list<float>  $real
     * @param  list<float>  $imaginary
     */
    private function fft1d(array &$real, array &$imaginary): void
    {
        $n = count($real);

        if ($n <= 1) {
            return;
        }

        $half = intdiv($n, 2);
        $evenReal = [];
        $evenImaginary = [];
        $oddReal = [];
        $oddImaginary = [];

        for ($i = 0; $i < $half; $i++) {
            $evenReal[] = $real[2 * $i];
            $evenImaginary[] = $imaginary[2 * $i];
            $oddReal[] = $real[2 * $i + 1];
            $oddImaginary[] = $imaginary[2 * $i + 1];
        }

        $this->fft1d($evenReal, $evenImaginary);
        $this->fft1d($oddReal, $oddImaginary);

        for ($k = 0; $k < $half; $k++) {
            $angle = -2 * M_PI * $k / $n;
            $wReal = cos($angle);
            $wImaginary = sin($angle);

            $tReal = $wReal * $oddReal[$k] - $wImaginary * $oddImaginary[$k];
            $tImaginary = $wReal * $oddImaginary[$k] + $wImaginary * $oddReal[$k];

            $real[$k] = $evenReal[$k] + $tReal;
            $imaginary[$k] = $evenImaginary[$k] + $tImaginary;
            $real[$k + $half] = $evenReal[$k] - $tReal;
            $imaginary[$k + $half] = $evenImaginary[$k] - $tImaginary;
        }
    }
}
