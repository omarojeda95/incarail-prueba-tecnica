<?php

namespace App\Http\Requests\Futbol;

use Illuminate\Foundation\Http\FormRequest;

class ListarJugadoresRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'equipo_id' => ['nullable', 'integer', 'min:1'],
            'posicion' => ['nullable', 'string', 'max:50'],
            'nacionalidad' => ['nullable', 'string', 'max:100'],
            'orden' => ['nullable', 'in:nombre,posicion,numero_camiseta,fecha_nacimiento,nacionalidad'],
            'direccion' => ['nullable', 'in:asc,desc'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string|int> */
    public function filtros(): array
    {
        return array_filter([
            'buscar' => $this->validated('buscar'),
            'equipo_id' => $this->filled('equipo_id') ? $this->integer('equipo_id') : null,
            'posicion' => $this->validated('posicion'),
            'nacionalidad' => $this->validated('nacionalidad'),
            'orden' => $this->validated('orden'),
            'direccion' => $this->validated('direccion'),
            'por_pagina' => $this->filled('por_pagina') ? $this->integer('por_pagina') : null,
        ], fn ($valor) => $valor !== null);
    }
}
