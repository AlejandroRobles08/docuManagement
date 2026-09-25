<?php

namespace App\Enums;

enum DocumentType: string
{
    case Ine = 'ine';
    case Pasaporte = 'pasaporte';
    case CartillaMilitar = 'cartilla_militar';
    case Curp = 'curp';
    case LicenciaConducir = 'licencia_conducir';
    case ActaNacimiento = 'acta_nacimiento';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Ine => 'INE / IFE',
            self::Pasaporte => 'Pasaporte',
            self::CartillaMilitar => 'Cartilla militar',
            self::Curp => 'CURP',
            self::LicenciaConducir => 'Licencia de conducir',
            self::ActaNacimiento => 'Acta de nacimiento',
            self::Otro => 'Otro documento oficial',
        };
    }

    /**
     * Texto de ayuda para el campo "Identificador" del formulario de subida:
     * aclara qué número se espera para cada tipo de documento, porque un
     * campo genérico invita a escribir texto descriptivo (p. ej. "Acta de
     * nacimiento") en vez de un identificador real y único, lo que provoca
     * falsos positivos de "persona duplicada" entre personas distintas que
     * cometen el mismo error (ver PersonMatcher::findDuplicate()).
     */
    public function identifierHint(): string
    {
        return match ($this) {
            self::Ine => 'Usa la Clave de Elector (debajo de la fotografía).',
            self::Pasaporte => 'Usa el número de pasaporte.',
            self::CartillaMilitar => 'Usa el número de cartilla militar.',
            self::Curp => 'Usa la CURP completa (18 caracteres).',
            self::LicenciaConducir => 'Usa el número de licencia.',
            self::ActaNacimiento => 'Usa el número de folio o de acta (no escribas "acta de nacimiento").',
            self::Otro => 'Usa un identificador único que traiga el documento (folio, número de serie, etc.).',
        };
    }

    /**
     * @return array<string, string> value => label, listo para un <select>.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
