<?php

namespace App\Application\Access;

use App\Domain\Access\Exceptions\AccessRuleViolation;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final class RoleManagement
{
    /** @param  list<string>  $permissions */
    public function create(string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($name, $permissions) {
            $role = Role::create(['name' => $name, 'guard_name' => config('acl.guard')]);
            $role->syncPermissions($permissions);

            return $role;
        });
    }

    /** @param  list<string>  $permissions */
    public function update(Role $role, string $name, array $permissions): Role
    {
        $this->ensureEditable($role);

        return DB::transaction(function () use ($role, $name, $permissions) {
            $role->update(['name' => $name]);
            $role->syncPermissions($permissions);

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        $this->ensureEditable($role);

        if ($role->users()->exists()) {
            throw new AccessRuleViolation('El rol tiene usuarios asignados; reasígnalos antes de eliminarlo.');
        }

        $role->delete();
    }

    public static function isProtected(Role $role): bool
    {
        return in_array($role->name, config('acl.protected_roles'), true);
    }

    private function ensureEditable(Role $role): void
    {
        if (self::isProtected($role)) {
            throw new AccessRuleViolation("El rol {$role->name} es del sistema y no se puede modificar.");
        }
    }
}
