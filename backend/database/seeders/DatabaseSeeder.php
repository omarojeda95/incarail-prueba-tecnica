<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Todos los seeders son idempotentes: ejecutarlos de nuevo no duplica ni reinicia datos. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesYPermisosSeeder::class,
            UsuariosSeeder::class,
            ClientePassportSeeder::class,
        ]);
    }
}
