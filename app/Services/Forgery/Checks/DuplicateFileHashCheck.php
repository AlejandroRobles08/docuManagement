<?php

namespace App\Services\Forgery\Checks;

use App\Models\Document;
use App\Services\Forgery\Contracts\ForgeryCheck;
use App\Services\Forgery\ForgeryFinding;
use Illuminate\Http\UploadedFile;

/**
 * Busca si el contenido exacto de este archivo (mismo hash SHA-256) ya fue
 * subido antes, y compara a qué PERSONA quedará asociado en cada caso -no el
 * número de documento, que ya es único por diseño (índice único en la base
 * de datos) y por sí solo no dice nada sobre si se trata de la misma
 * identidad-.
 *
 * Si el archivo ya existe para la MISMA persona a la que se va a asociar
 * esta subida, probablemente es una simple resubida (poco sospechoso). Si ya
 * existe para OTRA persona ya registrada, o esta subida va a crear una
 * persona nueva, es un indicio fuerte de que la misma imagen se está
 * reutilizando para dar de alta a otra identidad -sea una persona ya
 * existente en el sistema o una que se está creando ahora mismo-.
 */
class DuplicateFileHashCheck implements ForgeryCheck
{
    public function evaluate(UploadedFile $file, array $context): ForgeryFinding
    {
        $existing = Document::query()
            ->where('file_hash', $context['file_hash'])
            ->with('person')
            ->first();

        if (! $existing) {
            return ForgeryFinding::clear();
        }

        if ($context['person_id'] !== null && $existing->person_id === $context['person_id']) {
            return ForgeryFinding::flag(
                score: 20,
                reason: 'Este mismo archivo ya se había subido antes para esta misma persona.',
            );
        }

        return ForgeryFinding::flag(
            score: 65,
            reason: "Este mismo archivo (idéntico byte a byte) ya está registrado a nombre de otra persona distinta (\"{$existing->person->full_name}\"), lo que puede indicar que la misma imagen se está reutilizando para dar de alta a otra identidad.",
        );
    }
}
