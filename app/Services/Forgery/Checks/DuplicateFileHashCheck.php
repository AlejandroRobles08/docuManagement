<?php

namespace App\Services\Forgery\Checks;

use App\Models\Document;
use App\Services\Forgery\Contracts\ForgeryCheck;
use App\Services\Forgery\ForgeryFinding;
use Illuminate\Http\UploadedFile;

/**
 * Busca si el contenido exacto de este archivo (mismo hash SHA-256) ya fue
 * subido antes. Si ya existe con el MISMO número de documento, probablemente
 * es una simple resubida del mismo archivo (poco sospechoso). Si existe con
 * un número de documento DISTINTO, es un indicio fuerte de que la misma
 * imagen se está reutilizando para dar de alta a otra identidad.
 */
class DuplicateFileHashCheck implements ForgeryCheck
{
    public function evaluate(UploadedFile $file, array $context): ForgeryFinding
    {
        $existing = Document::query()
            ->where('file_hash', $context['file_hash'])
            ->first();

        if (! $existing) {
            return ForgeryFinding::clear();
        }

        if ($existing->document_number === $context['document_number']) {
            return ForgeryFinding::flag(
                score: 20,
                reason: 'Este mismo archivo ya se había subido antes para el mismo número de documento.',
            );
        }

        return ForgeryFinding::flag(
            score: 55,
            reason: "Este mismo archivo (idéntico byte a byte) ya está registrado con otro número de documento distinto ({$existing->document_number}).",
        );
    }
}
