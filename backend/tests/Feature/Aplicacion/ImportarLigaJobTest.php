<?php

namespace Tests\Feature\Aplicacion;

use App\Aplicacion\Futbol\ImportarLiga;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use App\Jobs\ImportarLigaJob;
use App\Models\Liga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Dobles\ProveedorFutbolEnMemoria as Proveedor;
use Tests\TestCase;

class ImportarLigaJobTest extends TestCase
{
    use RefreshDatabase;

    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proveedor = new Proveedor;
        $this->app->instance(ProveedorDatosFutbol::class, $this->proveedor);
    }

    #[Test]
    public function importa_la_liga(): void
    {
        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(), '2026', []));

        ImportarLigaJob::dispatchSync('PL');

        $this->assertTrue(Liga::findOrFail(2021)->estaImportada());
    }

    #[Test]
    public function sin_cupo_vuelve_a_la_cola_hasta_que_se_libere_uno(): void
    {
        $this->proveedor->fallarCon(new LimiteDePeticionesAlcanzado(30));
        $job = (new ImportarLigaJob('PL'))->withFakeQueueInteractions();

        $job->handle($this->app->make(ImportarLiga::class));

        $job->assertReleased();
        $this->assertGreaterThanOrEqual(31, $job->job->releaseDelay);
        $this->assertLessThanOrEqual(35, $job->job->releaseDelay);
    }

    #[Test]
    public function si_football_data_esta_caido_reintenta_un_minuto_despues(): void
    {
        $this->proveedor->fallarCon(new ProveedorNoDisponible('caído', sinConexion: true));
        $job = (new ImportarLigaJob('PL'))->withFakeQueueInteractions();

        $job->handle($this->app->make(ImportarLiga::class));

        $job->assertReleased(delay: 60);
    }

    #[Test]
    public function la_misma_liga_nunca_se_encola_dos_veces(): void
    {
        Queue::fake();

        ImportarLigaJob::dispatch('PL');
        ImportarLigaJob::dispatch('pl');
        ImportarLigaJob::dispatch(2014);

        Queue::assertPushed(ImportarLigaJob::class, 2);
    }
}
