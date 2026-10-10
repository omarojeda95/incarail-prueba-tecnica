<?php

namespace App\Aplicacion\Acceso;

use App\Models\Usuario;

/** Traduce los permisos del usuario a reglas CASL: [{action, subject}]. */
final class ReglasCasl
{
    /** @return list<array{action: string, subject: string}> */
    public function para(Usuario $usuario): array
    {
        $catalogo = config('acl.permisos');

        return $usuario->getAllPermissions()
            ->pluck('name')
            ->filter(fn (string $nombre) => isset($catalogo[$nombre]))
            ->sort()
            ->map(fn (string $nombre) => [
                'action' => $catalogo[$nombre]['accion'],
                'subject' => $catalogo[$nombre]['sujeto'],
            ])
            ->values()
            ->all();
    }
}
