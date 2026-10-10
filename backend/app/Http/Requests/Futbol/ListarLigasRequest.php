<?php

namespace App\Http\Requests\Futbol;

use Illuminate\Foundation\Http\FormRequest;

class ListarLigasRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'importadas' => ['nullable', 'in:0,1,true,false'],
            'disponibles' => ['nullable', 'in:0,1,true,false'],
        ];
    }

    /** @return array{buscar?: string, area?: string, importadas?: bool, disponibles?: bool} */
    public function filtros(): array
    {
        return array_filter([
            'buscar' => $this->validated('buscar'),
            'area' => $this->validated('area'),
            'importadas' => $this->filled('importadas') ? $this->boolean('importadas') : null,
            'disponibles' => $this->filled('disponibles') ? $this->boolean('disponibles') : null,
        ], fn ($valor) => $valor !== null);
    }
}
