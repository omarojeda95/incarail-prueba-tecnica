<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
| Every route except the login requires a valid Passport token from an active
| user. Authorization is decided here, on the server, through the permission
| of each route ("can:<permission>"), never only in the SPA.
*/

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:api', 'active', 'throttle:api'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // The five endpoints of the statement, with their paths as written.
    Route::get('competitions', [CompetitionController::class, 'index'])->middleware('can:competitions.read');
    Route::get('competitions/{competition_id}', [CompetitionController::class, 'show'])
        ->where('competition_id', '[0-9]+|[A-Za-z0-9]{2,6}')
        ->middleware('can:competitions.read');
    Route::get('team', [TeamController::class, 'index'])->middleware('can:teams.read');
    Route::get('team/{team_id}', [TeamController::class, 'show'])->whereNumber('team_id')->middleware('can:teams.read');
    Route::get('players', [PlayerController::class, 'index'])->middleware('can:players.read');

    // Extras used by the SPA.
    Route::post('competitions/{competition_id}/import', [CompetitionController::class, 'import'])->middleware('can:competitions.import');
    Route::get('players/{player_id}', [PlayerController::class, 'show'])->whereNumber('player_id')->middleware('can:players.read');

    Route::get('users', [UserController::class, 'index'])->middleware('can:users.read');
    Route::post('users', [UserController::class, 'store'])->middleware('can:users.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:users.read');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.update');
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('can:users.deactivate');
    Route::patch('users/{user}/activate', [UserController::class, 'activate'])->middleware('can:users.deactivate');

    Route::get('roles', [RoleController::class, 'index'])->middleware('can:roles.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('can:roles.manage');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.manage');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.manage');
    Route::get('permissions', [RoleController::class, 'permissions'])->middleware('can:roles.read');
});
