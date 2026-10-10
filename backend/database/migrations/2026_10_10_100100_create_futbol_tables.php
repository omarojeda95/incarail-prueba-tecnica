<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia local de football-data.org.
 *
 *  - Las claves primarias son los ids de football-data: cada importación es
 *    un upsert idempotente (no duplica registros).
 *  - Los textos que envía football-data son "text": no los controlamos y un
 *    valor largo en un varchar(255) abortaría la importación de toda la liga.
 *    Los códigos de formato conocido (PL, ARS, TIER_ONE) usan tipos cortos.
 *  - Toda clave foránea tiene índice (PostgreSQL no los crea solo) y la
 *    búsqueda por texto usa índices trigram. Mediciones en el README.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ligas', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->text('nombre');
            $table->string('codigo', 10)->nullable()->unique();
            $table->string('tipo', 20)->nullable();
            $table->text('emblema')->nullable();
            $table->string('plan', 20)->nullable();
            $table->text('area_nombre')->nullable();
            $table->string('area_codigo', 10)->nullable();
            $table->text('area_bandera')->nullable();
            $table->date('temporada_inicio')->nullable();
            $table->date('temporada_fin')->nullable();
            $table->unsignedSmallInteger('jornada_actual')->nullable();
            $table->timestamp('actualizado_proveedor_en')->nullable();
            // Null mientras la liga (equipos y planteles) no se haya importado.
            $table->timestamp('equipos_sincronizados_en')->nullable()->index();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });

        Schema::create('equipos', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->text('nombre')->index();
            $table->text('nombre_corto')->nullable();
            $table->string('sigla', 10)->nullable();
            $table->text('escudo')->nullable();
            $table->text('direccion')->nullable();
            $table->text('sitio_web')->nullable();
            $table->unsignedSmallInteger('fundacion')->nullable();
            $table->text('colores')->nullable();
            $table->text('estadio')->nullable();
            $table->text('area_nombre')->nullable();
            $table->text('entrenador')->nullable();
            $table->timestamp('actualizado_proveedor_en')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });

        Schema::create('liga_equipo', function (Blueprint $table) {
            $table->foreignId('liga_id')->constrained('ligas')->cascadeOnDelete();
            $table->foreignId('equipo_id')->index()->constrained('equipos')->cascadeOnDelete();
            $table->string('temporada', 9)->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();

            $table->primary(['liga_id', 'equipo_id']);
        });

        Schema::create('jugadores', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('equipo_id')->nullable()->index()->constrained('equipos')->nullOnDelete();
            $table->text('nombre')->index();
            $table->text('posicion')->nullable();
            $table->unsignedSmallInteger('numero_camiseta')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->text('nacionalidad')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });

        Schema::create('importaciones_liga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liga_id')->constrained('ligas')->cascadeOnDelete();
            $table->unsignedInteger('cantidad_equipos');
            $table->unsignedInteger('cantidad_jugadores');
            $table->string('origen', 20);
            $table->timestamp('importado_en');

            $table->index(['liga_id', 'importado_en']);
        });

        if (DB::getDriverName() === 'pgsql') {
            Schema::ensureExtensionExists('pg_trgm');

            foreach (['jugadores', 'equipos'] as $tabla) {
                Schema::table($tabla, fn (Blueprint $blueprint) => $blueprint
                    ->rawIndex('nombre gin_trgm_ops', "{$tabla}_nombre_trgm_index")
                    ->algorithm('gin'));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_liga');
        Schema::dropIfExists('jugadores');
        Schema::dropIfExists('liga_equipo');
        Schema::dropIfExists('equipos');
        Schema::dropIfExists('ligas');
    }
};
