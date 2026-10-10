<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('acl.guard');
        $todos = array_keys(config('acl.permisos'));

        foreach ($todos as $nombre) {
            Permission::findOrCreate($nombre, $guard);
        }

        foreach (config('acl.roles') as $nombre => $permisos) {
            $rol = Role::findOrCreate($nombre, $guard);

            // Admin siempre recibe todos los permisos; los demás roles solo se
            // siembran la primera vez, para conservar los cambios hechos desde la SPA.
            if ($permisos === ['*']) {
                $rol->syncPermissions($todos);
            } elseif ($rol->wasRecentlyCreated) {
                $rol->syncPermissions($permisos);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
