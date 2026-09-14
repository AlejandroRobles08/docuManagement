<?php

namespace App\Services\Forgery\Checks;

use App\Services\Forgery\Contracts\ForgeryCheck;
use App\Services\Forgery\ForgeryFinding;
use Illuminate\Http\UploadedFile;

/**
 * Revisa los metadatos EXIF (solo existen en JPEG/TIFF) en busca de la firma
 * de un software de edición de imágenes conocido. La AUSENCIA de EXIF no se
 * penaliza: es normalísima (WhatsApp, redes sociales y la mayoría de apps de
 * escaneo la eliminan al comprimir), así que solo generaría falsos positivos.
 */
class ExifMetadataCheck implements ForgeryCheck
{
    private const array EDITOR_SIGNATURES = [
        'photoshop', 'gimp', 'lightroom', 'affinity photo', 'paint.net',
        'pixlr', 'canva', 'snapseed', 'picsart', 'facetune',
    ];

    public function evaluate(UploadedFile $file, array $context): ForgeryFinding
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return ForgeryFinding::clear();
        }

        $exif = @exif_read_data($file->getRealPath());

        if (! is_array($exif)) {
            return ForgeryFinding::clear();
        }

        $software = trim((string) ($exif['Software'] ?? ''));

        if ($software === '') {
            return ForgeryFinding::clear();
        }

        foreach (self::EDITOR_SIGNATURES as $signature) {
            if (str_contains(strtolower($software), $signature)) {
                return ForgeryFinding::flag(
                    score: 30,
                    reason: "Los metadatos EXIF indican que la imagen fue procesada con software de edición (\"{$software}\").",
                );
            }
        }

        return ForgeryFinding::clear();
    }
}
