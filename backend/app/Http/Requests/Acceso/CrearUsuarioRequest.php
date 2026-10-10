<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CrearUsuarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'string', 'email', 'max:255', Rule::unique('usuarios', 'correo')],
            'contrasena' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', config('acl.guard'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => strtolower(trim((string) $this->input('correo')))]);
    }
}
