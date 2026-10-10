<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo users documented in the README (password: "password"). */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Ana Administradora', 'email' => 'admin@incarail.test', 'role' => 'admin'],
            ['name' => 'Luis Analista', 'email' => 'analista@incarail.test', 'role' => 'analista'],
            ['name' => 'Carla Consulta', 'email' => 'consulta@incarail.test', 'role' => 'consulta'],
        ];

        foreach ($users as $data) {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => 'password'],
            );

            if ($user->wasRecentlyCreated) {
                $user->assignRole($data['role']);
            }
        }
    }
}
