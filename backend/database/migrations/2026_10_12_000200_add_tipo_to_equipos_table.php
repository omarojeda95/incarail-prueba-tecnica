<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** enum crea una restricción CHECK: la base solo admite 'club' o 'seleccion'. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->enum('tipo', ['club', 'seleccion'])->default('club')->after('nombre');
        });

        // Los equipos ya guardados de una competición de selecciones (Mundial, Eurocopa) son selecciones.
        $competiciones = DB::table('ligas')->whereIn('codigo', config('futbol.competiciones_selecciones'))->pluck('id');
        DB::table('equipos')
            ->whereIn('id', DB::table('liga_equipo')->select('equipo_id')->whereIn('liga_id', $competiciones))
            ->update(['tipo' => 'seleccion']);

        DB::table('jugadores')
            ->whereIn('equipo_id', DB::table('equipos')->select('id')->where('tipo', 'seleccion'))
            ->update(['equipo_id' => null]);
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
