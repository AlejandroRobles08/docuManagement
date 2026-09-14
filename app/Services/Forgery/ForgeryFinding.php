<?php

namespace App\Services\Forgery;

/**
 * Resultado de una sola heurística (ver Contracts\ForgeryCheck). Cuando
 * $triggered es false, $score y $reason se ignoran: la heurística no
 * encontró nada raro (o simplemente no aplica a este tipo de archivo).
 */
final readonly class ForgeryFinding
{
    public function __construct(
        public bool $triggered,
        public int $score = 0,
        public string $reason = '',
    ) {}

    public static function clear(): self
    {
        return new self(triggered: false);
    }

    public static function flag(int $score, string $reason): self
    {
        return new self(triggered: true, score: $score, reason: $reason);
    }
}
