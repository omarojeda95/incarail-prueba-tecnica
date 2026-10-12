<?php

namespace Tests\Feature\BaseDeDatos;

use App\Aplicacion\Futbol\ImportarLiga;
use App\Dominio\Futbol\Datos\DatosEquipo;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use App\Models\Equipo;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Dobles\ProveedorFutbolEnMemoria as Proveedor;
use Tests\Soporte\InteractuaConAcceso;
use Tests\TestCase;

/** Reglas que revisaría un DBA. Corren sobre SQLite y sobre PostgreSQL (CI). */
class EsquemaTest extends TestCase
{
    use InteractuaConAcceso, RefreshDatabase;

    #[Test]
    public function toda_clave_foranea_de_las_tablas_de_futbol_tiene_indice(): void
    {
        foreach (['jugadores', 'liga_equipo', 'equipo_jugador', 'importaciones_liga'] as $tabla) {
            $columnasIniciales = collect(Schema::getIndexes($tabla))->map(fn (array $indice) => $indice['columns'][0]);

            foreach (Schema::getForeignKeys($tabla) as $claveForanea) {
                $columna = $claveForanea['columns'][0];
                $this->assertContains($columna, $columnasIniciales, "{$tabla}.{$columna} es clave foránea y no tiene índice.");
            }
        }
    }

    #[Test]
    public function el_tipo_de_equipo_solo_admite_club_o_seleccion(): void
    {
        $this->assertSame('club', Equipo::factory()->create()->refresh()->tipo);
        $this->assertSame('seleccion', Equipo::factory()->create(['tipo' => 'seleccion'])->refresh()->tipo);

        // La base rechaza cualquier otro valor (restricción CHECK).
        $this->expectException(QueryException::class);
        Equipo::factory()->create(['tipo' => 'otro']);
    }

    #[Test]
    public function borrar_un_usuario_no_deja_tokens_ni_roles_huerfanos(): void
    {
        // La API desactiva usuarios en vez de borrarlos, pero la base igual tiene que quedar consistente.
        $this->prepararAcceso();
        $usuario = $this->usuarioConRol('analista');
        $usuario->createToken('spa');

        $usuario->delete();

        // Tokens: clave foránea con borrado en cascada. Roles: relación polimórfica de spatie (sin FK
        // posible), que el paquete limpia al borrar el modelo.
        $this->assertDatabaseMissing('oauth_access_tokens', ['user_id' => $usuario->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $usuario->id]);
    }

    #[Test]
    public function las_busquedas_por_texto_tienen_indices_trigram_en_postgresql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pg_trgm solo existe en PostgreSQL.');
        }

        foreach (['jugadores', 'equipos'] as $tabla) {
            $trigram = collect(Schema::getIndexes($tabla))->firstWhere('name', "{$tabla}_nombre_trgm_index");
            $this->assertNotNull($trigram, "{$tabla}.nombre no tiene índice trigram.");
            $this->assertSame('gin', $trigram['type']);
        }
    }

    #[Test]
    public function el_correo_es_unico_sin_importar_mayusculas_en_la_base(): void
    {
        $fila = ['nombre' => 'Ana', 'contrasena' => 'x', 'creado_en' => now(), 'actualizado_en' => now()];
        DB::table('usuarios')->insert([...$fila, 'correo' => 'ana@incarail.test']);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('usuarios')->insert([...$fila, 'correo' => 'ANA@incarail.test']);
    }

    #[Test]
    public function los_textos_largos_de_football_data_no_rompen_una_importacion(): void
    {
        $urlLarga = 'https://crests.football-data.org/'.str_repeat('a', 300).'.svg';
        $base = Proveedor::equipo(57, 'Arsenal FC');
        $equipo = new DatosEquipo(
            $base->id, $base->nombre, $base->nombreCorto, $base->sigla, $urlLarga, str_repeat('Calle ', 60),
            $urlLarga, $base->fundacion, $base->colores, $base->estadio, $base->areaNombre, $base->entrenador, null,
        );
        $this->app->instance(ProveedorDatosFutbol::class, (new Proveedor)->conPlanteles(new PlantelesLiga(Proveedor::liga(), '2026', [$equipo])));

        $this->app->make(ImportarLiga::class)->ejecutar('PL');

        $this->assertSame($urlLarga, Equipo::findOrFail(57)->escudo);
    }
}
