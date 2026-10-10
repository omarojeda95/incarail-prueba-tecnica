<?php

/*
|--------------------------------------------------------------------------
| Access control list
|--------------------------------------------------------------------------
|
| Single source of truth for the permissions the API enforces. Each one maps
| to a CASL rule ({action, subject}) that the SPA receives on login.
| Permissions are fixed because the server checks them in code; roles are
| data: administrators can create roles and combine these permissions.
|
*/

return [

    'permissions' => [
        'competitions.read' => ['action' => 'read', 'subject' => 'Competition', 'description' => 'Ver ligas y su detalle (importa la liga si aún no está guardada)'],
        'competitions.import' => ['action' => 'import', 'subject' => 'Competition', 'description' => 'Forzar la importación / actualización de ligas en segundo plano'],
        'teams.read' => ['action' => 'read', 'subject' => 'Team', 'description' => 'Ver equipos guardados'],
        'players.read' => ['action' => 'read', 'subject' => 'Player', 'description' => 'Ver jugadores guardados'],
        'users.read' => ['action' => 'read', 'subject' => 'User', 'description' => 'Ver usuarios'],
        'users.create' => ['action' => 'create', 'subject' => 'User', 'description' => 'Crear usuarios'],
        'users.update' => ['action' => 'update', 'subject' => 'User', 'description' => 'Editar usuarios y asignarles roles'],
        'users.deactivate' => ['action' => 'deactivate', 'subject' => 'User', 'description' => 'Desactivar y reactivar usuarios'],
        'roles.read' => ['action' => 'read', 'subject' => 'Role', 'description' => 'Ver roles y permisos'],
        'roles.manage' => ['action' => 'manage', 'subject' => 'Role', 'description' => 'Crear, editar y eliminar roles'],
    ],

    'roles' => [
        'admin' => ['*'],
        'analista' => ['competitions.read', 'competitions.import', 'teams.read', 'players.read'],
        'consulta' => ['competitions.read', 'teams.read', 'players.read'],
    ],

    // Roles that cannot be renamed, edited or deleted from the API.
    'protected_roles' => ['admin'],

    'guard' => 'api',

];
