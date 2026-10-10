<?php

namespace App\Aplicacion\Acceso;

use App\Dominio\Acceso\Excepciones\ReglaDeAccesoViolada;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final class GestionRoles
{
    /** @param  list<string>  $permisos */
    public function crear(string $nombre, array $permisos): Role
    {
        return DB::transaction(function () use ($nombre, $permisos) {
            $rol = Role::create(['name' => $nombre, 'guard_name' => config('acl.guard')]);
            $rol->syncPermissions($permisos);

            return $rol;
        });
    }

    /** @param  list<string>  $permisos */
    public function editar(Role $rol, string $nombre, array $permisos): Role
    {
        $this->exigirEditable($rol);

        return DB::transaction(function () use ($rol, $nombre, $permisos) {
            $rol->update(['name' => $nombre]);
            $rol->syncPermissions($permisos);

            return $rol;
        });
    }

    public function eliminar(Role $rol): void
    {
        $this->exigirEditable($rol);

        if ($rol->users()->exists()) {
            throw new ReglaDeAccesoViolada('El rol tiene usuarios asignados; reasígnalos antes de eliminarlo.');
        }

        $rol->delete();
    }

    public static function esProtegido(Role $rol): bool
    {
        return in_array($rol->name, config('acl.roles_protegidos'), true);
    }

    private function exigirEditable(Role $rol): void
    {
        if (self::esProtegido($rol)) {
            throw new ReglaDeAccesoViolada("El rol {$rol->name} es del sistema y no se puede modificar.");
        }
    }
}
