<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/** Cliente de tokens personales con el que se emiten los tokens de la SPA. */
class ClientePassportSeeder extends Seeder
{
    public function run(ClientRepository $clientes): void
    {
        $existe = Passport::client()->newQuery()
            ->where('revoked', false)
            ->get()
            ->contains(fn ($cliente) => $cliente->hasGrantType('personal_access'));

        if (! $existe) {
            $clientes->createPersonalAccessGrantClient('SPA Inca Rail', 'usuarios');
        }
    }
}
