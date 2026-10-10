<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local copy of football-data.org. Primary keys are the football-data ids,
 * which makes every import an idempotent upsert (no duplicated rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('code', 10)->nullable()->unique();
            $table->string('type', 20)->nullable();
            $table->string('emblem')->nullable();
            $table->string('plan', 20)->nullable()->index();
            $table->string('area_name')->nullable();
            $table->string('area_code', 10)->nullable();
            $table->string('area_flag')->nullable();
            $table->date('season_start')->nullable();
            $table->date('season_end')->nullable();
            $table->unsignedSmallInteger('current_matchday')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            // Null until the competition (teams + squads) has been imported.
            $table->timestamp('teams_synced_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->index();
            $table->string('short_name')->nullable();
            $table->string('tla', 10)->nullable();
            $table->string('crest')->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->unsignedSmallInteger('founded')->nullable();
            $table->string('club_colors')->nullable();
            $table->string('venue')->nullable();
            $table->string('area_name')->nullable();
            $table->string('coach_name')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('competition_team', function (Blueprint $table) {
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('season', 9)->nullable();
            $table->timestamps();

            $table->primary(['competition_id', 'team_id']);
        });

        Schema::create('players', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->index();
            $table->string('position')->nullable()->index();
            $table->unsignedSmallInteger('shirt_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->timestamps();
        });

        Schema::create('competition_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('teams_count');
            $table->unsignedInteger('players_count');
            $table->string('trigger', 20);
            $table->timestamp('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_imports');
        Schema::dropIfExists('players');
        Schema::dropIfExists('competition_team');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('competitions');
    }
};
