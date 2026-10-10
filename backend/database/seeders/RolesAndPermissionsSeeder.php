<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('acl.guard');
        $all = array_keys(config('acl.permissions'));

        foreach ($all as $name) {
            Permission::findOrCreate($name, $guard);
        }

        foreach (config('acl.roles') as $name => $permissions) {
            $role = Role::findOrCreate($name, $guard);

            // Admin always gets every permission; other roles are only seeded
            // the first time so later changes made from the SPA are kept.
            if ($permissions === ['*']) {
                $role->syncPermissions($all);
            } elseif ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
