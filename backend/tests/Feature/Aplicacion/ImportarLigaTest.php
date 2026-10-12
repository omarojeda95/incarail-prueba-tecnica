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
        $this->assertDatabaseHas('jugadores', ['id' => 1, 'nombre' => 'David Raya', 'posicion' => 'Goalkeeper', 'numero_camiseta' => 22]);
        $this->assertSame(57, Jugador::findOrFail(1)->club()?->id);
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

        $this->assertDatabaseHas('jugadores', ['id' => 2, 'numero_camiseta' => 7]);
        $this->assertSame(65, Jugador::findOrFail(2)->club()?->id);
        // Una liga trae los planteles completos: Saka sale del plantel del Arsenal.
        $this->assertDatabaseMissing('equipo_jugador', ['equipo_id' => 57, 'jugador_id' => 2]);
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

    #[Test]
    public function una_competicion_de_selecciones_no_le_quita_el_club_a_sus_jugadores(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());
        $this->importar()->ejecutar('PL');

        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(2000, 'WC'), '2026', [
            Proveedor::equipo(770, 'England', [
                Proveedor::jugador(2, 'Bukayo Saka', 'Offence', 7),
                Proveedor::jugador(90, 'Jugador Sin Club', 'Defence', 4),
            ]),
        ]));
        $this->importar()->ejecutar('WC');

        // Saka sigue en el Arsenal, con su número del club.
        $this->assertDatabaseHas('jugadores', ['id' => 2, 'numero_camiseta' => null]);
        $this->assertSame(57, Jugador::findOrFail(2)->club()?->id);
        // Quien llega primero por su selección no tiene club conocido y está en el plantel de su selección.
        $this->assertNull(Jugador::findOrFail(90)->club());
        $this->assertDatabaseHas('equipo_jugador', ['equipo_id' => 770, 'jugador_id' => 90]);
        $this->assertDatabaseHas('liga_equipo', ['liga_id' => 2000, 'equipo_id' => 770]);

        // Al volver a importar la liga de clubes, el jugador nuevo de la selección no se toca
        // y los del club siguen en su club.
        $this->importar()->ejecutar('PL');
        $this->assertSame(57, Jugador::findOrFail(2)->club()?->id);
        $this->assertDatabaseHas('equipo_jugador', ['equipo_id' => 770, 'jugador_id' => 2]);
    }

    #[Test]
    public function cada_equipo_se_guarda_como_club_o_como_seleccion(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());
        $this->importar()->ejecutar('PL');
        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(2000, 'WC'), '2026', [
            Proveedor::equipo(770, 'England', [Proveedor::jugador(2, 'Bukayo Saka', 'Offence', 7)]),
        ]));
        $this->importar()->ejecutar('WC');

        $this->assertDatabaseHas('equipos', ['id' => 57, 'tipo' => 'club']);
        $this->assertDatabaseHas('equipos', ['id' => 770, 'tipo' => 'seleccion']);
        // El club actual de un jugador siempre es un club.
        $this->assertSame('club', Jugador::findOrFail(2)->club()?->tipo);
    }

    #[Test]
    public function un_jugador_pertenece_a_su_club_y_a_su_seleccion(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());
        $this->importar()->ejecutar('PL');
        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(2000, 'WC'), '2026', [
            Proveedor::equipo(770, 'England', [Proveedor::jugador(2, 'Bukayo Saka', 'Offence', 7)]),
        ]));
        $this->importar()->ejecutar('WC');

        // Un solo registro del jugador, con su club…
        $this->assertSame(1, Jugador::whereKey(2)->count());
        $this->assertSame(57, Jugador::findOrFail(2)->club()?->id);
        // …y en el plantel de los dos equipos.
        $this->assertDatabaseHas('equipo_jugador', ['equipo_id' => 57, 'jugador_id' => 2]);
        $this->assertDatabaseHas('equipo_jugador', ['equipo_id' => 770, 'jugador_id' => 2]);
        $this->assertEqualsCanonicalizing([57, 770], Jugador::findOrFail(2)->equipos()->pluck('equipos.id')->all());
        $this->assertSame(['Bukayo Saka'], Equipo::findOrFail(770)->jugadores()->pluck('nombre')->all());

        // Importar de nuevo no duplica las pertenencias.
        $this->importar()->ejecutar('WC');
        $this->assertSame(2, Jugador::findOrFail(2)->equipos()->count());
    }

    #[Test]
    public function un_jugador_repetido_en_dos_equipos_no_rompe_la_importacion(): void
    {
        // football-data a veces lista a un jugador en dos planteles (traspaso o préstamo).
        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(), '2026', [
            Proveedor::equipo(57, 'Arsenal FC', [Proveedor::jugador(1, 'David Raya', 'Goalkeeper', 22)]),
            Proveedor::equipo(65, 'Manchester City FC', [Proveedor::jugador(1, 'David Raya', 'Goalkeeper', 1)]),
        ]));

        $this->importar()->ejecutar('PL');

        $this->assertSame(1, Jugador::count());
        $this->assertDatabaseHas('jugadores', ['id' => 1, 'numero_camiseta' => 1]);
        $this->assertDatabaseHas('equipo_jugador', ['equipo_id' => 57, 'jugador_id' => 1]);
        $this->assertDatabaseHas('equipo_jugador', ['equipo_id' => 65, 'jugador_id' => 1]);
    }

    #[Test]
    public function una_copa_solo_agrega_jugadores_y_no_vacia_planteles(): void
    {
        $this->proveedor->conPlanteles($this->premierLeague());
        $this->importar()->ejecutar('PL');

        // La Champions envía planteles incompletos: no puede sacar a nadie del plantel de su club.
        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(2001, 'CL', tipo: 'CUP'), '2026', [
            Proveedor::equipo(57, 'Arsenal FC', [Proveedor::jugador(1, 'David Raya', 'Goalkeeper', 22)]),
        ]));
        $this->importar()->ejecutar('CL');

        $this->assertDatabaseHas('equipo_jugador', ['equipo_id' => 57, 'jugador_id' => 2]);
        $this->assertSame(57, Jugador::findOrFail(2)->club()?->id);
    }

    #[Test]
    public function el_club_actual_es_el_de_la_ultima_liga_importada_que_lo_trae(): void
    {
        // Saka se va a préstamo a otro club de otra liga: su club actual pasa a ser ese.
        $this->proveedor->conPlanteles($this->premierLeague());
        $this->importar()->ejecutar('PL');
        $this->travel(1)->minutes();
        $this->proveedor->conPlanteles(new PlantelesLiga(Proveedor::liga(2014, 'PD'), '2026', [
            Proveedor::equipo(86, 'Real Madrid CF', [Proveedor::jugador(2, 'Bukayo Saka', 'Offence', 11)]),
        ]));
        $this->importar()->ejecutar('PD');

        $this->assertSame(86, Jugador::findOrFail(2)->club()?->id);
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
