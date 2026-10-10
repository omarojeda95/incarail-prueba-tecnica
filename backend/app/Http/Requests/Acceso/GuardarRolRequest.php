<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sirve tanto para crear como para editar un rol. */
class GuardarRolRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('roles', 'name')->where('guard_name', config('acl.guard'))->ignore($this->route('rol')),
            ],
            'permisos' => ['present', 'array'],
            'permisos.*' => ['string', Rule::in(array_keys(config('acl.permisos')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => strtolower(trim((string) $this->input('nombre')))]);
    }

    public function messages(): array
    {
        return ['nombre.regex' => 'El nombre solo admite minúsculas, números, guiones y guiones bajos.'];
    }
}
