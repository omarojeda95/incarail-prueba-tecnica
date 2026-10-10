<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/** Personal access client used to issue the SPA tokens on login. */
class PassportClientSeeder extends Seeder
{
    public function run(ClientRepository $clients): void
    {
        $exists = Passport::client()->newQuery()
            ->where('revoked', false)
            ->get()
            ->contains(fn ($client) => $client->hasGrantType('personal_access'));

        if (! $exists) {
            $clients->createPersonalAccessGrantClient('Inca Rail SPA', 'users');
        }
    }
}
