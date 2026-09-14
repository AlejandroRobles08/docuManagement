<?php

namespace App\Services;

use App\Models\Person;

final readonly class PersonMatch
{
    public function __construct(
        public Person $person,
        public string $matchedBy,
        public string $message,
    ) {}
}
