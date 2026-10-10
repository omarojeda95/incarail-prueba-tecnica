<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' FC';

        return [
            'id' => fake()->unique()->numberBetween(1, 999999),
            'name' => $name,
            'short_name' => $name,
            'tla' => strtoupper(fake()->lexify('???')),
            'founded' => fake()->numberBetween(1850, 2000),
            'venue' => fake()->streetName().' Stadium',
            'area_name' => fake()->country(),
        ];
    }
}
