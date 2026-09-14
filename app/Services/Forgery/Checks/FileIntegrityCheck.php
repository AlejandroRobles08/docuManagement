<?php

namespace App\Services\Forgery\Checks;

use App\Services\Forgery\Contracts\ForgeryCheck;
use App\Services\Forgery\ForgeryFinding;
use Illuminate\Http\UploadedFile;

/**
 * Compara la extensión del archivo original contra el tipo MIME real,
 * detectado a partir del contenido (no del nombre) del archivo. Un archivo
 * cuya extensión no corresponde a su contenido real (p. ej. un ".jpg" que en
 * realidad es un PDF, o viceversa) es un indicio -leve- de manipulación o de
 * un intento de camuflar el tipo real del archivo.
 */
class FileIntegrityCheck implements ForgeryCheck
{
    private const array EXTENSIONS_BY_MIME = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'application/pdf' => ['pdf'],
    ];

    public function evaluate(UploadedFile $file, array $context): ForgeryFinding
    {
        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        $expectedExtensions = self::EXTENSIONS_BY_MIME[$mime] ?? null;

        if ($expectedExtensions === null) {
            // Tipo no reconocido por esta heurística: la validación de la
            // petición (mimes:jpg,jpeg,png,pdf) ya se encarga de rechazarlo.
            return ForgeryFinding::clear();
        }

        if (in_array($extension, $expectedExtensions, true)) {
            return ForgeryFinding::clear();
        }

        return ForgeryFinding::flag(
            score: 15,
            reason: "La extensión del archivo (.{$extension}) no coincide con su contenido real ({$mime}).",
        );
    }
}
