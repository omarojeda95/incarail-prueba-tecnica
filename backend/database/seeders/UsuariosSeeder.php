<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

/** Usuarios de prueba documentados en el README (contraseña: "password"). */
class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            ['nombre' => 'Ana Administradora', 'correo' => 'admin@incarail.test', 'rol' => 'admin'],
            ['nombre' => 'Luis Analista', 'correo' => 'analista@incarail.test', 'rol' => 'analista'],
            ['nombre' => 'Carla Consulta', 'correo' => 'consulta@incarail.test', 'rol' => 'consulta'],
        ];

        foreach ($usuarios as $datos) {
            $usuario = Usuario::query()->firstOrCreate(
                ['correo' => $datos['correo']],
                ['nombre' => $datos['nombre'], 'contrasena' => 'password', 'activo' => true],
            );

            if ($usuario->wasRecentlyCreated) {
                $usuario->assignRole($datos['rol']);
            }
        }
    }
}
