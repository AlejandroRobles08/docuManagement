<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'curp' => strtoupper($this->faker->bothify('????######?????##')),
            'birth_date' => $this->faker->dateTimeBetween('-70 years', '-18 years'),
        ];
    }
}
