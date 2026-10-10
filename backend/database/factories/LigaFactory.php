<?php

namespace Database\Factories;

use App\Models\Liga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Liga>
 */
class LigaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1000, 999999),
            'nombre' => 'Liga '.fake()->unique()->words(2, true),
            'codigo' => strtoupper(fake()->unique()->lexify('???')),
            'tipo' => 'LEAGUE',
            'plan' => 'TIER_ONE',
            'area_nombre' => fake()->country(),
            'area_codigo' => strtoupper(fake()->lexify('???')),
            'temporada_inicio' => '2026-08-15',
            'temporada_fin' => '2027-05-23',
        ];
    }

    public function importada(): static
    {
        return $this->state(['equipos_sincronizados_en' => now()]);
    }
}
