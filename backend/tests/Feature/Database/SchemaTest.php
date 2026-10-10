<?php

namespace Tests\Feature\Database;

use App\Application\Football\ImportCompetition;
use App\Domain\Football\Data\CompetitionSquads;
use App\Domain\Football\Data\TeamData;
use App\Domain\Football\Ports\FootballDataProvider;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fakes\InMemoryFootballDataProvider as Fake;
use Tests\TestCase;

/** Database invariants a DBA would check, run on SQLite and on PostgreSQL (CI). */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_foreign_key_of_the_football_tables_is_indexed(): void
    {
        foreach (['players', 'competition_team', 'competition_imports'] as $table) {
            $leadingColumns = collect(Schema::getIndexes($table))->map(fn (array $index) => $index['columns'][0]);

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $column = $foreignKey['columns'][0];
                $this->assertContains($column, $leadingColumns, "{$table}.{$column} is a foreign key without an index.");
            }
        }
    }

    #[Test]
    public function substring_searches_have_trigram_indexes_on_postgresql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pg_trgm only exists in PostgreSQL.');
        }

        foreach (['players', 'teams'] as $table) {
            $trigram = collect(Schema::getIndexes($table))->firstWhere('name', "{$table}_name_trgm_index");
            $this->assertNotNull($trigram, "{$table}.name has no trigram index.");
            $this->assertSame('gin', $trigram['type']);
        }
    }

    #[Test]
    public function emails_are_unique_regardless_of_case_at_database_level(): void
    {
        $row = ['name' => 'Ana', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()];
        DB::table('users')->insert([...$row, 'email' => 'ana@incarail.test']);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('users')->insert([...$row, 'email' => 'ANA@incarail.test']);
    }

    #[Test]
    public function long_texts_from_football_data_do_not_break_an_import(): void
    {
        $longUrl = 'https://crests.football-data.org/'.str_repeat('a', 300).'.svg';
        $team = Fake::team(57, 'Arsenal FC');
        $team = new TeamData(
            $team->id, $team->name, $team->shortName, $team->tla, $longUrl, str_repeat('Street ', 60),
            $longUrl, $team->founded, $team->clubColors, $team->venue, $team->areaName, $team->coachName, null,
        );
        $this->app->instance(FootballDataProvider::class, (new Fake)->withSquads(new CompetitionSquads(Fake::competition(), '2026', [$team])));

        $this->app->make(ImportCompetition::class)->handle('PL');

        $this->assertSame($longUrl, Team::findOrFail(57)->crest);
    }
}
