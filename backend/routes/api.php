<?php

use App\Http\Controllers\Api\AutenticacionController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\JugadorController;
use App\Http\Controllers\Api\LigaController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| Todas las rutas, salvo el inicio de sesión, exigen un token de Passport
| válido de un usuario activo. La autorización se decide aquí, en el
| servidor, con el permiso de cada ruta ("can:<permiso>"), nunca solo en la SPA.
|
| Las cinco rutas del enunciado conservan exactamente su forma en inglés
| (/competitions, /team, /players); el resto está en español.
*/

Route::post('auth/iniciar-sesion', [AutenticacionController::class, 'iniciarSesion'])->middleware('throttle:inicio-sesion');

Route::middleware(['auth:api', 'activo', 'throttle:api'])->group(function () {
    Route::get('auth/sesion', [AutenticacionController::class, 'sesion']);
    Route::post('auth/cerrar-sesion', [AutenticacionController::class, 'cerrarSesion']);

    // Los cinco endpoints del enunciado, con sus rutas tal como están escritas.
    Route::get('competitions', [LigaController::class, 'index'])->middleware('can:ligas.ver');
    Route::get('competitions/{competition_id}', [LigaController::class, 'show'])
        ->where('competition_id', '[0-9]+|[A-Za-z0-9]{2,6}')
        ->middleware('can:ligas.ver');
    Route::get('team', [EquipoController::class, 'index'])->middleware('can:equipos.ver');
    Route::get('team/{team_id}', [EquipoController::class, 'show'])->whereNumber('team_id')->middleware('can:equipos.ver');
    Route::get('players', [JugadorController::class, 'index'])->middleware('can:jugadores.ver');

    // Extras que usa la SPA.
    Route::post('competitions/{competition_id}/importar', [LigaController::class, 'importar'])->middleware('can:ligas.importar');
    Route::get('players/{player_id}', [JugadorController::class, 'show'])->whereNumber('player_id')->middleware('can:jugadores.ver');

    Route::get('usuarios', [UsuarioController::class, 'index'])->middleware('can:usuarios.ver');
    Route::post('usuarios', [UsuarioController::class, 'store'])->middleware('can:usuarios.crear');
    Route::get('usuarios/{usuario}', [UsuarioController::class, 'show'])->middleware('can:usuarios.ver');
    Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('can:usuarios.editar');
    Route::patch('usuarios/{usuario}/desactivar', [UsuarioController::class, 'desactivar'])->middleware('can:usuarios.desactivar');
    Route::patch('usuarios/{usuario}/activar', [UsuarioController::class, 'activar'])->middleware('can:usuarios.desactivar');

    Route::get('roles', [RolController::class, 'index'])->middleware('can:roles.ver');
    Route::post('roles', [RolController::class, 'store'])->middleware('can:roles.gestionar');
    Route::put('roles/{rol}', [RolController::class, 'update'])->middleware('can:roles.gestionar');
    Route::delete('roles/{rol}', [RolController::class, 'destroy'])->middleware('can:roles.gestionar');
    Route::get('permisos', [RolController::class, 'permisos'])->middleware('can:roles.ver');
});
