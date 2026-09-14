<?php

namespace App\Enums;

enum DocumentType: string
{
    case Ine = 'ine';
    case Pasaporte = 'pasaporte';
    case CartillaMilitar = 'cartilla_militar';
    case Curp = 'curp';
    case LicenciaConducir = 'licencia_conducir';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Ine => 'INE / IFE',
            self::Pasaporte => 'Pasaporte',
            self::CartillaMilitar => 'Cartilla militar',
            self::Curp => 'CURP',
            self::LicenciaConducir => 'Licencia de conducir',
            self::Otro => 'Otro documento oficial',
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
