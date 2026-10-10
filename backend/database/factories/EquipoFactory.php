<?php

namespace Database\Factories;

use App\Models\Equipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipo>
 */
class EquipoFactory extends Factory
{
    public function definition(): array
    {
        $nombre = fake()->unique()->city().' FC';

        return [
            'id' => fake()->unique()->numberBetween(1, 999999),
            'nombre' => $nombre,
            'nombre_corto' => $nombre,
            'sigla' => strtoupper(fake()->lexify('???')),
            'fundacion' => fake()->numberBetween(1850, 2000),
            'estadio' => 'Estadio '.fake()->streetName(),
            'area_nombre' => fake()->country(),
        ];
    }
}
