<?php

namespace App\Http\Requests\Football;

use Illuminate\Foundation\Http\FormRequest;

class ListCompetitionsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'imported' => ['nullable', 'in:0,1,true,false'],
            'available' => ['nullable', 'in:0,1,true,false'],
        ];
    }

    /** @return array{search?: string, area?: string, imported?: bool, available?: bool} */
    public function filters(): array
    {
        return array_filter([
            'search' => $this->validated('search'),
            'area' => $this->validated('area'),
            'imported' => $this->filled('imported') ? $this->boolean('imported') : null,
            'available' => $this->filled('available') ? $this->boolean('available') : null,
        ], fn ($value) => $value !== null);
    }
}
