<?php

namespace App\Aplicacion\Acceso;

use App\Dominio\Acceso\Excepciones\ReglaDeAccesoViolada;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class GestionUsuarios
{
    /** @param  array{buscar?: string, por_pagina?: int}  $filtros */
    public function paginar(array $filtros): LengthAwarePaginator
    {
        return Usuario::query()
            ->with('roles')
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $texto) => $query->where(
                fn (Builder $query) => $query->whereLike('nombre', "%{$texto}%")->orWhereLike('correo', "%{$texto}%")
            ))
            ->orderBy('nombre')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();
    }

    /** @param  array{nombre: string, correo: string, contrasena: string, roles?: list<string>}  $datos */
    public function crear(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            $usuario = Usuario::query()->create([
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'contrasena' => $datos['contrasena'],
                'activo' => true,
            ]);
            $usuario->syncRoles($datos['roles'] ?? []);

            return $usuario;
        });
    }

    /** @param  array{nombre?: string, correo?: string, contrasena?: string|null, roles?: list<string>}  $datos */
    public function editar(Usuario $usuario, array $datos): Usuario
    {
        if (isset($datos['roles']) && $usuario->hasRole('admin') && ! in_array('admin', $datos['roles'], true)) {
            $this->exigirOtroAdminActivo($usuario, 'No se puede quitar el rol admin al último administrador activo.');
        }

        return DB::transaction(function () use ($usuario, $datos) {
            $usuario->fill(array_filter([
                'nombre' => $datos['nombre'] ?? null,
                'correo' => $datos['correo'] ?? null,
                'contrasena' => $datos['contrasena'] ?? null,
            ]))->save();

            if (isset($datos['roles'])) {
                $usuario->syncRoles($datos['roles']);
            }

            return $usuario;
        });
    }

    public function desactivar(Usuario $usuario, Usuario $actor): Usuario
    {
        if ($usuario->is($actor)) {
            throw new ReglaDeAccesoViolada('No puedes desactivar tu propia cuenta.');
        }

        if ($usuario->hasRole('admin')) {
            $this->exigirOtroAdminActivo($usuario, 'No se puede desactivar al último administrador activo.');
        }

        $usuario->forceFill(['activo' => false, 'desactivado_en' => now()])->save();

        // Cierra en el acto todas las sesiones abiertas de ese usuario.
        $usuario->tokens()->update(['revoked' => true]);

        return $usuario;
    }

    public function activar(Usuario $usuario): Usuario
    {
        $usuario->forceFill(['activo' => true, 'desactivado_en' => null])->save();

        return $usuario;
    }

    private function exigirOtroAdminActivo(Usuario $usuario, string $mensaje): void
    {
        $hayOtros = Usuario::role('admin')->where('activo', true)->whereKeyNot($usuario->getKey())->exists();

        if (! $hayOtros) {
            throw new ReglaDeAccesoViolada($mensaje);
        }
    }
}
