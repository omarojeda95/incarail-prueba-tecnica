<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EditarUsuarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'correo' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('usuarios', 'correo')->ignore($this->route('usuario'))],
            'contrasena' => ['nullable', 'string', Password::min(8)->letters()->numbers()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', config('acl.guard'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('correo')) {
            $this->merge(['correo' => strtolower(trim((string) $this->input('correo')))]);
        }
    }
}
