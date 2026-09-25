<?php

namespace App\Services\Forgery\Checks;

use App\Services\Forgery\Contracts\ForgeryCheck;
use App\Services\Forgery\ForgeryFinding;
use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Detecta fotos borrosas con la "varianza del Laplaciano": un filtro
 * Laplaciano resalta los bordes (cambios bruscos de brillo) de la imagen.
 * Una foto nítida tiene muchos bordes marcados -letras, líneas, el borde del
 * propio documento-, así que esos valores varían mucho de un píxel a otro.
 * Una foto borrosa suaviza esos bordes, así que el Laplaciano da valores
 * mucho más parejos entre sí (varianza baja).
 *
 * Es la misma técnica que usan herramientas como OpenCV para esto (ahí
 * conocida como "variance of Laplacian"), reimplementada aquí solo con GD
 * para no agregar una dependencia nueva.
 *
 * Un documento borroso no es necesariamente falso, pero sí es un problema
 * práctico (el staff no puede verificar bien los datos a simple vista), así
 * que aporta un riesgo "medio" por sí solo: no debe pasar desapercibido,
 * pero tampoco debe bloquear la subida como lo hace un riesgo alto.
 */
class BlurDetectionCheck implements ForgeryCheck
{
    private const int ANALYSIS_SIZE = 256;

    /**
     * Por debajo de esta varianza se considera borrosa. Calibrado a mano
     * contra documentos reales del sistema (nítidos: ~1100-6000) y contra
     * versiones de esos mismos documentos difuminadas artificialmente en
     * distintos grados (~280 ya se ve claramente borrosa, ~130 es ilegible).
     * Es una heurística, no una medición calibrada contra un estándar
     * externo: ajústala si en la práctica se ven muchos falsos positivos o
     * negativos.
     */
    private const float VARIANCE_THRESHOLD = 300.0;

    /**
     * Puntuación fija en el umbral de "riesgo medio" (ver
     * config/forgery.php: medium_threshold): un documento borroso, por sí
     * solo, debe quedar marcado como riesgo medio, ni bajo ni alto.
     */
    private const int SCORE = 50;

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
            $resized = $this->resizeGrayscale($image);

            try {
                $variance = $this->laplacianVariance($resized);
            } finally {
                imagedestroy($resized);
            }
        } finally {
            imagedestroy($image);
        }

        if ($variance >= self::VARIANCE_THRESHOLD) {
            return ForgeryFinding::clear();
        }

        return ForgeryFinding::flag(
            score: self::SCORE,
            reason: sprintf(
                'La imagen parece borrosa (nitidez %.1f, por debajo del mínimo esperado de %.1f), lo que dificulta verificar los datos del documento a simple vista.',
                $variance,
                self::VARIANCE_THRESHOLD,
            ),
        );
    }

    private function resizeGrayscale(GdImage $image): GdImage
    {
        $size = self::ANALYSIS_SIZE;
        $resized = imagecreatetruecolor($size, $size);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
        imagefilter($resized, IMG_FILTER_GRAYSCALE);

        return $resized;
    }

    private function laplacianVariance(GdImage $image): float
    {
        $size = self::ANALYSIS_SIZE;

        // Ya está en escala de grises (R, G y B son iguales), así que basta
        // con leer un canal.
        $pixels = [];
        for ($y = 0; $y < $size; $y++) {
            $row = [];
            for ($x = 0; $x < $size; $x++) {
                $row[] = imagecolorat($image, $x, $y) & 0xFF;
            }
            $pixels[] = $row;
        }

        $responses = [];

        for ($y = 1; $y < $size - 1; $y++) {
            for ($x = 1; $x < $size - 1; $x++) {
                $responses[] = 4 * $pixels[$y][$x]
                    - $pixels[$y - 1][$x]
                    - $pixels[$y + 1][$x]
                    - $pixels[$y][$x - 1]
                    - $pixels[$y][$x + 1];
            }
        }

        $count = count($responses);
        $mean = array_sum($responses) / $count;

        $variance = 0.0;
        foreach ($responses as $value) {
            $variance += ($value - $mean) ** 2;
        }

        return $variance / $count;
    }
}
