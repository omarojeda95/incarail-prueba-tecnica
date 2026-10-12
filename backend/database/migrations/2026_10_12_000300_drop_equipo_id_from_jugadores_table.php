<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** El club sale del plantel (equipo_jugador), así que equipo_id sobra. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropForeign(['equipo_id']);
            $table->dropIndex(['equipo_id']);
            $table->dropColumn('equipo_id');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->foreignId('equipo_id')->nullable()->after('id')->index()->constrained('equipos')->nullOnDelete();
        });

        // Vuelve a calcular el club de cada jugador a partir de su plantel.
        DB::table('equipo_jugador')
            ->join('equipos', 'equipos.id', '=', 'equipo_jugador.equipo_id')
            ->where('equipos.tipo', 'club')
            ->orderBy('equipo_jugador.actualizado_en')
            ->get(['equipo_jugador.jugador_id', 'equipo_jugador.equipo_id'])
            ->each(fn ($fila) => DB::table('jugadores')->where('id', $fila->jugador_id)->update(['equipo_id' => $fila->equipo_id]));
    }
};
