<?php

namespace App\Services\Forgery\Checks;

use App\Services\Forgery\Contracts\ForgeryCheck;
use App\Services\Forgery\ForgeryFinding;
use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Detecta rayones o marcas dibujadas sobre el documento con una app de
 * edición (línea, garabato, tachón), sin importar el color.
 *
 * La idea: un documento escaneado o fotografiado es, casi siempre, de baja
 * saturación (tinta negra sobre papel claro, quizás algún sello o elemento
 * oficial de color). Una marca dibujada encima -sobre todo con la
 * herramienta "pincel" o "lápiz" de cualquier app- suele usar un color
 * mucho más puro/vivo que cualquier cosa impresa en el documento original.
 * Se mide la saturación (HSV) de cada píxel y se busca qué porción de la
 * imagen es "de color vivo": ni tan poca que sea solo ruido de compresión,
 * ni tanta que sea simplemente una fotografía a color normal.
 *
 * Limitación conocida: una marca en blanco/negro/gris (sin color) no se
 * puede distinguir así del propio contenido del documento, y no se analizan
 * PDFs (requeriría renderizar la página a imagen, que no es posible solo
 * con GD sin agregar una dependencia nueva).
 */
class ScratchMarkCheck implements ForgeryCheck
{
    private const int ANALYSIS_SIZE = 256;

    /**
     * Saturación HSV (0-1) a partir de la cual un píxel se considera "de
     * color vivo/artificial". Calibrado contra credenciales reales: incluso
     * con la foto de una persona, un escudo o detalles de diseño a color,
     * menos del 0.05% de los píxeles supera 0.6 de saturación; un rayón
     * dibujado con una app (rojo, azul, verde "puro" de pincel/marcador)
     * suele rondar 0.8-1.0.
     */
    private const float SATURATION_THRESHOLD = 0.7;

    /**
     * Además de saturado, se exige un brillo medio: los extremos (casi
     * negro, casi blanco) dan lecturas de saturación ruidosas por la
     * compresión JPEG, sin que haya color real detrás.
     */
    private const int MIN_VALUE = 40;

    private const int MAX_VALUE = 240;

    /**
     * Una raya ocupa una porción pequeña de la imagen. Por debajo de este
     * mínimo es ruido de compresión; por encima del máximo, es simplemente
     * una fotografía a color normal (no una marca puntual añadida).
     */
    private const float MIN_COVERAGE = 0.005;

    private const float MAX_COVERAGE = 0.12;

    private const int SCORE = 40;

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
            $resized = $this->resize($image);

            try {
                $coverage = $this->vividPixelCoverage($resized);
            } finally {
                imagedestroy($resized);
            }
        } finally {
            imagedestroy($image);
        }

        if ($coverage < self::MIN_COVERAGE || $coverage > self::MAX_COVERAGE) {
            return ForgeryFinding::clear();
        }

        $percentage = round($coverage * 100, 1);

        return ForgeryFinding::flag(
            score: self::SCORE,
            reason: "Se detectó una marca de color vivo sobre el {$percentage}% de la imagen, poco común en un documento escaneado o fotografiado, típica de un rayón o garabato añadido con una app de edición.",
        );
    }

    private function resize(GdImage $image): GdImage
    {
        $size = self::ANALYSIS_SIZE;
        $resized = imagecreatetruecolor($size, $size);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));

        return $resized;
    }

    private function vividPixelCoverage(GdImage $image): float
    {
        $size = self::ANALYSIS_SIZE;
        $vividCount = 0;

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $max = max($r, $g, $b);
                $min = min($r, $g, $b);

                if ($max < self::MIN_VALUE || $max > self::MAX_VALUE) {
                    continue;
                }

                $saturation = $max === 0 ? 0.0 : ($max - $min) / $max;

                if ($saturation >= self::SATURATION_THRESHOLD) {
                    $vividCount++;
                }
            }
        }

        return $vividCount / ($size * $size);
    }
}
