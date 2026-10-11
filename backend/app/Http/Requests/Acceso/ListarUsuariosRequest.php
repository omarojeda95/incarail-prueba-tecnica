<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;

class ListarUsuariosRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{buscar?: string, por_pagina?: int} */
    public function filtros(): array
    {
        return array_filter([
            'buscar' => $this->validated('buscar'),
            'por_pagina' => $this->filled('por_pagina') ? $this->integer('por_pagina') : null,
        ], fn ($valor) => $valor !== null);
    }
}
