<?php

// Only the rules this API uses.
return [
    'array' => 'El campo :attribute debe ser una lista.',
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
    ],
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El valor seleccionado en :attribute no existe.',
    'in' => 'El valor seleccionado en :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'string' => 'El campo :attribute no debe superar los :max caracteres.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'password' => [
        'letters' => 'La :attribute debe contener al menos una letra.',
        'numbers' => 'La :attribute debe contener al menos un número.',
    ],
    'present' => 'El campo :attribute debe estar presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El :attribute ya está en uso.',

    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo',
        'password' => 'contraseña',
        'roles' => 'roles',
        'roles.*' => 'rol',
        'permissions' => 'permisos',
        'permissions.*' => 'permiso',
        'search' => 'búsqueda',
        'per_page' => 'tamaño de página',
        'competition_id' => 'liga',
        'team_id' => 'equipo',
    ],
];
