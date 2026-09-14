<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\RiskLevel;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'uploaded_by' => User::factory(),
            'document_type' => $this->faker->randomElement(DocumentType::cases())->value,
            'document_number' => strtoupper($this->faker->bothify('??######')),
            'disk' => 'public',
            'file_path' => 'documents/'.$this->faker->uuid().'.jpg',
            'original_filename' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => $this->faker->numberBetween(50_000, 4_000_000),
            'file_hash' => hash('sha256', $this->faker->uuid()),
            'risk_score' => $this->faker->numberBetween(0, 100),
            'risk_level' => $this->faker->randomElement(RiskLevel::cases())->value,
            'risk_reasons' => [],
            'authenticity_confirmed_by_staff' => false,
        ];
    }
}
