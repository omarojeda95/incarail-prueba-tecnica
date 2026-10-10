<?php

namespace App\Http\Requests\Football;

use Illuminate\Foundation\Http\FormRequest;

class ListPlayersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'team_id' => ['nullable', 'integer', 'min:1'],
            'position' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:name,position,shirt_number,date_of_birth,nationality'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string|int> */
    public function filters(): array
    {
        return array_filter([
            'search' => $this->validated('search'),
            'team_id' => $this->filled('team_id') ? $this->integer('team_id') : null,
            'position' => $this->validated('position'),
            'nationality' => $this->validated('nationality'),
            'sort' => $this->validated('sort'),
            'direction' => $this->validated('direction'),
            'per_page' => $this->filled('per_page') ? $this->integer('per_page') : null,
        ], fn ($value) => $value !== null);
    }
}
