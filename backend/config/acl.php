<?php

/*
|--------------------------------------------------------------------------
| Lista de control de acceso
|--------------------------------------------------------------------------
|
| Fuente única de los permisos que verifica la API. Cada uno equivale a una
| regla CASL ({action, subject}) que la SPA recibe al iniciar sesión.
| Los permisos son fijos porque el servidor los verifica en el código; los
| roles son datos: un administrador puede crear roles y combinar permisos.
|
*/

return [

    'permisos' => [
        'ligas.ver' => ['accion' => 'ver', 'sujeto' => 'Liga', 'descripcion' => 'Ver ligas y su detalle (importa la liga si aún no está guardada)'],
        'ligas.importar' => ['accion' => 'importar', 'sujeto' => 'Liga', 'descripcion' => 'Forzar la importación / actualización de ligas en segundo plano'],
        'equipos.ver' => ['accion' => 'ver', 'sujeto' => 'Equipo', 'descripcion' => 'Ver equipos guardados'],
        'jugadores.ver' => ['accion' => 'ver', 'sujeto' => 'Jugador', 'descripcion' => 'Ver jugadores guardados'],
        'usuarios.ver' => ['accion' => 'ver', 'sujeto' => 'Usuario', 'descripcion' => 'Ver usuarios'],
        'usuarios.crear' => ['accion' => 'crear', 'sujeto' => 'Usuario', 'descripcion' => 'Crear usuarios'],
        'usuarios.editar' => ['accion' => 'editar', 'sujeto' => 'Usuario', 'descripcion' => 'Editar usuarios y asignarles roles'],
        'usuarios.desactivar' => ['accion' => 'desactivar', 'sujeto' => 'Usuario', 'descripcion' => 'Desactivar y reactivar usuarios'],
        'roles.ver' => ['accion' => 'ver', 'sujeto' => 'Rol', 'descripcion' => 'Ver roles y permisos'],
        'roles.gestionar' => ['accion' => 'gestionar', 'sujeto' => 'Rol', 'descripcion' => 'Crear, editar y eliminar roles'],
    ],

    'roles' => [
        'admin' => ['*'],
        'analista' => ['ligas.ver', 'ligas.importar', 'equipos.ver', 'jugadores.ver'],
        'consulta' => ['ligas.ver', 'equipos.ver', 'jugadores.ver'],
    ],

    // Roles que no se pueden renombrar, editar ni eliminar desde la API.
    'roles_protegidos' => ['admin'],

    'guard' => 'api',

];
