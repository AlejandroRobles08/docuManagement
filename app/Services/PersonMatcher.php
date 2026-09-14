<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Person;

/**
 * Decide si la persona descrita en el formulario de subida ya existe en la
 * base de datos. Se compara, en este orden, por:
 *
 *   1. CURP (si se capturó): es el identificador único de personas físicas
 *      en México, así que una coincidencia aquí es prácticamente segura.
 *   2. El par (tipo de documento, número de documento): si ese documento
 *      exacto ya está registrado, es la misma persona (o al menos el mismo
 *      documento) sin importar el nombre que se haya escrito.
 *   3. Nombre completo + fecha de nacimiento idénticos: más débil que las
 *      anteriores (dos personas distintas podrían compartir nombre y fecha
 *      de nacimiento), pero sigue siendo una señal fuerte de duplicado
 *      cuando el número de documento se escribió distinto o con errores.
 */
class PersonMatcher
{
    public function findDuplicate(array $data): ?PersonMatch
    {
        $curp = isset($data['curp']) ? strtoupper(trim($data['curp'])) : null;

        if ($curp) {
            $person = Person::query()->where('curp', $curp)->first();

            if ($person) {
                return new PersonMatch($person, 'curp', "Ya existe una persona registrada con el CURP {$curp}.");
            }
        }

        $existingDocument = Document::query()
            ->where('document_type', $data['document_type'])
            ->where('document_number', $data['document_number'])
            ->first();

        if ($existingDocument) {
            return new PersonMatch(
                $existingDocument->person,
                'document_number',
                'Ese número de documento ya está registrado para otra persona en el sistema.'
            );
        }

        $person = Person::query()
            ->whereRaw('LOWER(full_name) = ?', [strtolower(trim($data['full_name']))])
            ->when(
                ! empty($data['birth_date']),
                fn ($query) => $query->whereDate('birth_date', $data['birth_date']),
                fn ($query) => $query->whereNull('birth_date'),
            )
            ->first();

        if ($person) {
            return new PersonMatch(
                $person,
                'name_birthdate',
                'Ya existe una persona registrada con el mismo nombre y fecha de nacimiento.'
            );
        }

        return null;
    }
}
