<?php

namespace Tests\Feature\Aplicacion;

use App\Aplicacion\Futbol\ImportarLiga;
use App\Dominio\Futbol\Datos\DatosLiga;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Eventos\LigaImportada;
use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use App\Models\Equipo;
use App\Models\ImportacionLiga;
use App\Models\Jugador;
use App\Models\Liga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Dobles\ProveedorFutbolEnMemoria as Proveedor;
use Tests\TestCase;

class ImportarLigaTest extends TestCase
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
    public function guarda_la_liga_sus_equipos_y_sus_jugadores(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());

        $liga = $this->importar()->ejecutar('PL');

        $this->assertSame(2021, $liga->id);
        $this->assertTrue($liga->estaImportada());
        $this->assertSame(2, Equipo::count());
        $this->assertSame(3, Jugador::count());
        $this->assertEqualsCanonicalizing([57, 65], $liga->equipos()->pluck('equipos.id')->all());
        $this->assertDatabaseHas('jugadores', ['id' => 1, 'nombre' => 'David Raya', 'posicion' => 'Goalkeeper', 'numero_camiseta' => 22, 'equipo_id' => 57]);
    }

    #[Test]
    public function importar_dos_veces_la_misma_liga_no_duplica_registros(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());

        $this->importar()->ejecutar(2021);
        $this->importar()->ejecutar('PL');

        $this->assertSame(1, Liga::count());
        $this->assertSame(2, Equipo::count());
        $this->assertSame(3, Jugador::count());
        $this->assertSame(2, Liga::find(2021)->equipos()->count());
    }

    #[Test]
    public function una_nueva_importacion_actualiza_lo_que_cambio(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());
        $this->importar()->ejecutar(2021);

        // Saka pasa al Manchester City y recibe número de camiseta.
        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(), '2026', [
            Proveedor::equipo(57, 'Arsenal FC', [Proveedor::jugador(1, 'David Raya', 'Goalkeeper', 22)]),
            Proveedor::equipo(65, 'Manchester City FC', [Proveedor::jugador(2, 'Bukayo Saka', 'Offence', 7), Proveedor::jugador(3, 'Erling Haaland', 'Offence', 9)]),
        ]));
        $this->importar()->ejecutar(2021);

        $this->assertDatabaseHas('jugadores', ['id' => 2, 'equipo_id' => 65, 'numero_camiseta' => 7]);
        $this->assertSame(3, Jugador::count());
    }

    #[Test]
    public function los_equipos_que_salen_de_la_liga_se_desvinculan_y_se_actualiza_la_temporada(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());
        $this->importar()->ejecutar('PL');

        // Temporada siguiente: el Manchester City ya no está, sigue el Arsenal y sube el Ipswich.
        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(), '2027', [
            Proveedor::equipo(57, 'Arsenal FC', [Proveedor::jugador(1, 'David Raya', 'Goalkeeper', 22)]),
            Proveedor::equipo(349, 'Ipswich Town FC', [Proveedor::jugador(9, 'Leif Davis', 'Defence', 3)]),
        ]));
        $this->importar()->ejecutar('PL');

        $vinculos = Liga::findOrFail(2021)->equipos()->orderBy('equipos.id')->get();
        $this->assertSame([57, 349], $vinculos->pluck('id')->all());
        $this->assertSame(['2027', '2027'], $vinculos->pluck('pivot.temporada')->all());
        $this->assertTrue(Equipo::whereKey(65)->exists(), 'El equipo se conserva; solo se quita el vínculo.');
    }

    #[Test]
    public function conserva_los_datos_del_catalogo_que_el_endpoint_de_planteles_no_envia(): void
    {
        Liga::factory()->create(['id' => 2021, 'codigo' => 'PL', 'plan' => 'TIER_ONE', 'area_nombre' => 'England']);
        $parcial = new PlantelesLiga(
            new DatosLiga(2021, 'Premier League', 'PL', 'LEAGUE', null, null, null, null, null, null, null, 7, null),
            '2026',
            [],
        );
        $this->proveedor->conPlanteles($parcial);

        $this->importar()->ejecutar(2021);

        $this->assertDatabaseHas('ligas', ['id' => 2021, 'plan' => 'TIER_ONE', 'area_nombre' => 'England', 'jornada_actual' => 7]);
    }

    #[Test]
    public function anuncia_la_importacion_con_un_evento_de_dominio(): void
    {
        Event::fake([LigaImportada::class]);
        $this->proveedor->conPlanteles($this->premierLeague());

        $this->importar()->ejecutar('PL', origen: 'cola');

        Event::assertDispatched(LigaImportada::class, fn (LigaImportada $evento) => $evento->ligaId === 2021
            && $evento->cantidadEquipos === 2
            && $evento->cantidadJugadores === 3
            && $evento->origen === 'cola');
    }

    #[Test]
    public function la_importacion_queda_registrada_en_la_auditoria(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());

        $this->importar()->ejecutar('PL');

        $this->assertSame(1, ImportacionLiga::where('liga_id', 2021)->where('cantidad_jugadores', 3)->count());
    }

    private function importar(): ImportarLiga
    {
        return $this->app->make(ImportarLiga::class);
    }

    private function premierLeague(): PlantelesLiga
    {
        return new PlantelesLiga(Proveedor::liga(), '2026', [
            Proveedor::equipo(57, 'Arsenal FC', [Proveedor::jugador(1, 'David Raya', 'Goalkeeper', 22), Proveedor::jugador(2, 'Bukayo Saka', 'Offence')]),
            Proveedor::equipo(65, 'Manchester City FC', [Proveedor::jugador(3, 'Erling Haaland', 'Offence', 9)]),
        ]);
    }
}
