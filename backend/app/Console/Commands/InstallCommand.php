<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** One idempotent command to get a working API: used by Docker and by the manual setup. */
#[Signature('app:install')]
#[Description('Run migrations, create the Passport keys and seed roles, permissions and demo users')]
class InstallCommand extends Command
{
    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->call('passport:keys');
        }

        $this->call('db:seed', ['--force' => true]);

        $this->components->info('Ready. Demo users: admin@incarail.test / analista@incarail.test / consulta@incarail.test (password: "password").');

        return self::SUCCESS;
    }
}
