<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used both to create and to update a role. */
class SaveRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('roles', 'name')->where('guard_name', config('acl.guard'))->ignore($this->route('role')),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('acl.permissions')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => strtolower(trim((string) $this->input('name')))]);
    }

    public function messages(): array
    {
        return ['name.regex' => 'El nombre solo admite minúsculas, números, guiones y guiones bajos.'];
    }
}
