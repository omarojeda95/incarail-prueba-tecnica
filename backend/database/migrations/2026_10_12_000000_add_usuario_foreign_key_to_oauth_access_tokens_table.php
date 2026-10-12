<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Passport crea user_id sin clave foránea; aquí todo token es de un usuario y se borra con él. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
