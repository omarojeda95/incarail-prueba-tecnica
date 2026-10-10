<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Findings of the database review (measured with 150k players, see README):
 *  - PostgreSQL does not index foreign keys by itself: the team detail went
 *    from 654 ms to 8 ms with players(team_id).
 *  - ILIKE '%term%' cannot use a b-tree; trigram GIN indexes make the
 *    searches ~20x faster (PostgreSQL only).
 *  - Texts that come from football-data are not under our control: an
 *    overlong value in varchar(255) would abort the whole league import.
 *  - Emails must be unique regardless of case at database level.
 */
return new class extends Migration
{
    /** Free texts written by football-data (codes like PL/ARS keep their short types). */
    private const PROVIDER_TEXTS = [
        'competitions' => ['name' => false, 'emblem' => true, 'area_name' => true, 'area_flag' => true],
        'teams' => [
            'name' => false, 'short_name' => true, 'crest' => true, 'address' => true, 'website' => true,
            'club_colors' => true, 'venue' => true, 'area_name' => true, 'coach_name' => true,
        ],
        'players' => ['name' => false, 'position' => true, 'nationality' => true],
    ];

    public function up(): void
    {
        Schema::table('players', fn (Blueprint $table) => $table->index('team_id'));
        Schema::table('competition_team', fn (Blueprint $table) => $table->index('team_id'));
        Schema::table('competition_imports', fn (Blueprint $table) => $table->index(['competition_id', 'imported_at']));

        $this->changeProviderTexts(toText: true);

        // Expression index: the only DDL the schema builder cannot express.
        DB::statement('create unique index users_email_lower_unique on users (lower(email))');

        if (DB::getDriverName() === 'pgsql') {
            Schema::ensureExtensionExists('pg_trgm');

            foreach (['players', 'teams'] as $table) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint
                    ->rawIndex('name gin_trgm_ops', "{$table}_name_trgm_index")
                    ->algorithm('gin'));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('players', fn (Blueprint $table) => $table->dropIndex('players_name_trgm_index'));
            Schema::table('teams', fn (Blueprint $table) => $table->dropIndex('teams_name_trgm_index'));
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_email_lower_unique'));

        $this->changeProviderTexts(toText: false);

        Schema::table('competition_imports', fn (Blueprint $table) => $table->dropIndex(['competition_id', 'imported_at']));
        Schema::table('competition_team', fn (Blueprint $table) => $table->dropIndex(['team_id']));
        Schema::table('players', fn (Blueprint $table) => $table->dropIndex(['team_id']));
    }

    private function changeProviderTexts(bool $toText): void
    {
        foreach (self::PROVIDER_TEXTS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $toText) {
                foreach ($columns as $column => $nullable) {
                    ($toText ? $table->text($column) : $table->string($column))->nullable($nullable)->change();
                }
            });
        }
    }
};
