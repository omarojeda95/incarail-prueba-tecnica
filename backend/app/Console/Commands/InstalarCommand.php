<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:instalar')]
#[Description('Ejecuta migraciones, crea las llaves de Passport y siembra roles, permisos y usuarios de prueba')]
class InstalarCommand extends Command
{
    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->call('passport:keys');
        }

        $this->call('db:seed', ['--force' => true]);

        $this->components->info('Listo. Usuarios de prueba: admin@incarail.test / analista@incarail.test / consulta@incarail.test (contraseña: "password").');

        return self::SUCCESS;
    }
}
