<?php

namespace App\Enums;

enum RiskLevel: string
{
    case Bajo = 'bajo';
    case Medio = 'medio';
    case Alto = 'alto';

    public function label(): string
    {
        return match ($this) {
            self::Bajo => 'Riesgo bajo',
            self::Medio => 'Riesgo medio',
            self::Alto => 'Riesgo alto',
        };
    }

    /**
     * Clase de UIkit para pintar la insignia de riesgo (ver risk-badge en _custom.scss).
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Bajo => 'risk-badge risk-badge--bajo',
            self::Medio => 'risk-badge risk-badge--medio',
            self::Alto => 'risk-badge risk-badge--alto',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Bajo => 'check',
            self::Medio => 'warning',
            self::Alto => 'ban',
        };
    }

    /**
     * Si es true, el staff debe confirmar explícitamente antes de guardar el documento.
     */
    public function requiresManualConfirmation(): bool
    {
        return $this === self::Alto;
    }

    public static function fromScore(int $score): self
    {
        $medium = (int) config('forgery.medium_threshold');
        $high = (int) config('forgery.high_threshold');

        return match (true) {
            $score >= $high => self::Alto,
            $score >= $medium => self::Medio,
            default => self::Bajo,
        };
    }
}
