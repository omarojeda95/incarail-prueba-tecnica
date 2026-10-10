<?php

namespace App\Http\Requests\Football;

use Illuminate\Foundation\Http\FormRequest;

class ListTeamsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'competition_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{search?: string, competition_id?: int, per_page?: int} */
    public function filters(): array
    {
        return array_filter([
            'search' => $this->validated('search'),
            'competition_id' => $this->filled('competition_id') ? $this->integer('competition_id') : null,
            'per_page' => $this->filled('per_page') ? $this->integer('per_page') : null,
        ], fn ($value) => $value !== null);
    }
}
