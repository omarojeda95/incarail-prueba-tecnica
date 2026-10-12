<?php

namespace App\Http\Requests\Futbol;

use Illuminate\Foundation\Http\FormRequest;

class ListarEquiposRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'liga_id' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable', 'in:club,seleccion'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{buscar?: string, liga_id?: int, tipo?: string, por_pagina?: int} */
    public function filtros(): array
    {
        return array_filter([
            'buscar' => $this->validated('buscar'),
            'liga_id' => $this->filled('liga_id') ? $this->integer('liga_id') : null,
            'tipo' => $this->validated('tipo'),
            'por_pagina' => $this->filled('por_pagina') ? $this->integer('por_pagina') : null,
        ], fn ($valor) => $valor !== null);
    }
}
