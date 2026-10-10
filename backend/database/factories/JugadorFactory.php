<?php

namespace Database\Factories;

use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jugador>
 */
class JugadorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 9999999),
            'equipo_id' => Equipo::factory(),
            'nombre' => fake()->name(),
            'posicion' => fake()->randomElement(['Goalkeeper', 'Defence', 'Midfield', 'Offence']),
            'numero_camiseta' => fake()->numberBetween(1, 99),
            'fecha_nacimiento' => fake()->date(max: '2008-01-01'),
            'nacionalidad' => fake()->country(),
        ];
    }
}
