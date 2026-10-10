<?php

namespace App\Console\Commands;

use App\Aplicacion\Futbol\SincronizarCatalogoLigas;
use App\Dominio\Futbol\Excepciones\ExcepcionDatosFutbol;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('futbol:sincronizar-ligas')]
#[Description('Actualiza el catálogo de ligas desde football-data.org (1 petición)')]
class SincronizarLigasCommand extends Command
{
    public function handle(SincronizarCatalogoLigas $sincronizar): int
    {
        try {
            $this->components->info("{$sincronizar->ejecutar()} ligas sincronizadas.");

            return self::SUCCESS;
        } catch (ExcepcionDatosFutbol $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
