<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 9999999),
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'position' => fake()->randomElement(['Goalkeeper', 'Defence', 'Midfield', 'Offence']),
            'shirt_number' => fake()->numberBetween(1, 99),
            'date_of_birth' => fake()->date(max: '2008-01-01'),
            'nationality' => fake()->country(),
        ];
    }
}
