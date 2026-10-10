<?php

namespace Database\Factories;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->unique()->words(2, true).' League',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'type' => 'LEAGUE',
            'plan' => 'TIER_ONE',
            'area_name' => fake()->country(),
            'area_code' => strtoupper(fake()->lexify('???')),
            'season_start' => '2026-08-15',
            'season_end' => '2027-05-23',
        ];
    }

    public function imported(): static
    {
        return $this->state(['teams_synced_at' => now()]);
    }
}
