<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Plantel: un jugador puede estar en su club y en su selección. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipo_jugador', function (Blueprint $table) {
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('jugador_id')->index()->constrained('jugadores')->cascadeOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();

            $table->primary(['equipo_id', 'jugador_id']);
        });

        // Los planteles que ya estaban guardados pasan a la tabla nueva.
        DB::table('equipo_jugador')->insertUsing(
            ['equipo_id', 'jugador_id'],
            DB::table('jugadores')->select('equipo_id', 'id')->whereNotNull('equipo_id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_jugador');
    }
};
