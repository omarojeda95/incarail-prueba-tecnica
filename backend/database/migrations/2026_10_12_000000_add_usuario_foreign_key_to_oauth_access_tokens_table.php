<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passport crea oauth_access_tokens.user_id sin clave foránea (admite tokens sin usuario).
 * Aquí todos los tokens son de un usuario: si se borrara uno, sus tokens se van con él.
 */
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
