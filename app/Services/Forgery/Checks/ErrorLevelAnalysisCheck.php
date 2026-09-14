<?php

namespace App\Services\Forgery\Checks;

use App\Services\Forgery\Contracts\ForgeryCheck;
use App\Services\Forgery\ForgeryFinding;
use Illuminate\Http\UploadedFile;

/**
 * Error Level Analysis (ELA) simplificado, hecho solo con GD (sin Imagick ni
 * servicios externos).
 *
 * La idea: al volver a guardar una imagen JPEG con una calidad de compresión
 * fija, las zonas que ya estaban "originales" pierden información de forma
 * bastante uniforme. Si una región de la foto fue pegada/editada y viene de
 * una generación de compresión distinta (por ejemplo, una fotografía o un
 * sello superpuestos), esa región suele mostrar una diferencia notablemente
 * distinta al recomprimirla, comparada con el resto de la imagen.
 *
 * Esto NO es una prueba forense definitiva -es una heurística- por lo que
 * solo contribuye una puntuación moderada al riesgo total, nunca por sí sola
 * un rechazo automático.
 */
class ErrorLevelAnalysisCheck implements ForgeryCheck
{
    private const int ANALYSIS_SIZE = 256;

    private const int GRID = 16;

    private const int RECOMPRESS_QUALITY = 90;

    public function evaluate(UploadedFile $file, array $context): ForgeryFinding
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('imagecreatefromjpeg')) {
            return ForgeryFinding::clear();
        }

        $original = @imagecreatefromjpeg($file->getRealPath());

        if ($original === false) {
            return ForgeryFinding::clear();
        }

        $resizedOriginal = null;

        try {
            $size = self::ANALYSIS_SIZE;
            $resizedOriginal = imagecreatetruecolor($size, $size);
            imagecopyresampled($resizedOriginal, $original, 0, 0, 0, 0, $size, $size, imagesx($original), imagesy($original));

            $tmpPath = tempnam(sys_get_temp_dir(), 'ela_').'.jpg';
            imagejpeg($resizedOriginal, $tmpPath, self::RECOMPRESS_QUALITY);
            $recompressed = @imagecreatefromjpeg($tmpPath);
            @unlink($tmpPath);

            if ($recompressed === false) {
                return ForgeryFinding::clear();
            }

            $blockMeans = $this->blockDifferenceMeans($resizedOriginal, $recompressed, $size);
            imagedestroy($recompressed);

            return $this->scoreFromBlockMeans($blockMeans);
        } finally {
            imagedestroy($original);
            if ($resizedOriginal) {
                imagedestroy($resizedOriginal);
            }
        }
    }

    /**
     * @return list<float>
     */
    private function blockDifferenceMeans(\GdImage $original, \GdImage $recompressed, int $size): array
    {
        $blockSize = intdiv($size, self::GRID);
        $blockMeans = [];

        for ($by = 0; $by < self::GRID; $by++) {
            for ($bx = 0; $bx < self::GRID; $bx++) {
                $total = 0;
                $count = 0;

                for ($y = $by * $blockSize; $y < ($by + 1) * $blockSize; $y++) {
                    for ($x = $bx * $blockSize; $x < ($bx + 1) * $blockSize; $x++) {
                        $c1 = imagecolorat($original, $x, $y);
                        $c2 = imagecolorat($recompressed, $x, $y);

                        $total += abs((($c1 >> 16) & 0xFF) - (($c2 >> 16) & 0xFF))
                               + abs((($c1 >> 8) & 0xFF) - (($c2 >> 8) & 0xFF))
                               + abs(($c1 & 0xFF) - ($c2 & 0xFF));
                        $count++;
                    }
                }

                $blockMeans[] = $total / max(1, $count);
            }
        }

        return $blockMeans;
    }

    /**
     * @param  list<float>  $blockMeans
     */
    private function scoreFromBlockMeans(array $blockMeans): ForgeryFinding
    {
        $totalBlocks = count($blockMeans);
        $overallMean = array_sum($blockMeans) / $totalBlocks;

        if ($overallMean < 0.5) {
            // La imagen apenas cambió al recomprimirla (documento muy simple,
            // o ya venía muy comprimido): no hay señal suficiente.
            return ForgeryFinding::clear();
        }

        $variance = array_sum(array_map(
            fn (float $mean): float => ($mean - $overallMean) ** 2,
            $blockMeans
        )) / $totalBlocks;
        $stdDev = sqrt($variance);

        $threshold = $overallMean + max(2.5 * $stdDev, $overallMean * 2);
        $outlierCount = count(array_filter($blockMeans, fn (float $mean): bool => $mean > $threshold));

        // Un puñado de bloques distintos es la señal que buscamos (edición
        // localizada). Si casi toda la imagen es "distinta", es simplemente
        // una foto con mucho detalle/ruido, no un indicio de manipulación.
        if ($outlierCount === 0 || $outlierCount > $totalBlocks * 0.2) {
            return ForgeryFinding::clear();
        }

        return ForgeryFinding::flag(
            score: 35,
            reason: "El análisis de niveles de error (ELA) detectó {$outlierCount} zona(s) con un patrón de compresión distinto al resto de la imagen, lo que puede indicar edición localizada (por ejemplo, una foto, texto o sello pegados sobre el documento original).",
        );
    }
}
