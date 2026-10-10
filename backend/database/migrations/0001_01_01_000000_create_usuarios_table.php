<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza la tabla "users" del esqueleto de Laravel. La API funciona con
 * tokens (sin sesiones) y no tiene recuperación de contraseña, por eso no se
 * crean "sessions" ni "password_reset_tokens".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('correo');
            $table->string('contrasena');
            $table->boolean('activo')->default(true);
            $table->timestamp('desactivado_en')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });

        // Único sin importar mayúsculas (Ana@x.com = ana@x.com); cubre también la
        // unicidad simple, así que no hace falta otro índice. Índice por
        // expresión: el único DDL que el schema builder de Laravel no expresa.
        DB::statement('create unique index usuarios_correo_minusculas_unique on usuarios (lower(correo))');
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
