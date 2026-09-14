<?php

namespace App\Services\Forgery;

use App\Enums\RiskLevel;

final readonly class ForgeryReport
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $score,
        public RiskLevel $level,
        public array $reasons,
        public string $fileHash,
    ) {}

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'level' => $this->level->value,
            'reasons' => $this->reasons,
            'file_hash' => $this->fileHash,
        ];
    }
}
