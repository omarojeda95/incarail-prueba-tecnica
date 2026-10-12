<?php

namespace App\Console\Commands;

use App\Aplicacion\Futbol\ImportarLiga;
use App\Dominio\Futbol\Excepciones\ExcepcionDatosFutbol;
use App\Dominio\Futbol\Puertos\ColaDeImportaciones;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('futbol:importar {liga* : Ids o códigos, p. ej. PL 2014 SA} {--cola : Encolar las importaciones en lugar de ejecutarlas ahora}')]
#[Description('Importa ligas (equipos y planteles) desde football-data.org')]
class ImportarLigaCommand extends Command
{
    public function handle(ImportarLiga $importar, ColaDeImportaciones $cola): int
    {
        $estado = self::SUCCESS;

        foreach ($this->argument('liga') as $liga) {
            if ($this->option('cola')) {
                $cola->encolar($liga, 'consola');
                $this->components->info("{$liga}: en cola.");

                continue;
            }

            try {
                $importada = $importar->ejecutar($liga, 'consola');
                $this->components->info("{$liga}: {$importada->nombre} importada.");
            } catch (ExcepcionDatosFutbol $e) {
                $this->components->error("{$liga}: {$e->getMessage()}");
                $estado = self::FAILURE;
            }
        }

        return $estado;
    }
}
