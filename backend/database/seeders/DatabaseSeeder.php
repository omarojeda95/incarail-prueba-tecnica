<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Every seeder is idempotent: running it again never duplicates nor resets data. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            PassportClientSeeder::class,
        ]);
    }
}
