<?php

namespace App\Application\Football;

use App\Domain\Football\Data\CompetitionData;
use App\Domain\Football\Data\CompetitionSquads;
use App\Domain\Football\Data\TeamData;
use App\Domain\Football\Events\CompetitionImported;
use App\Domain\Football\Ports\FootballDataProvider;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\Player;
use App\Models\Team;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Command: copy a competition, its teams and their squads into our database.
 * Costs exactly one football-data request. Every write is an upsert keyed by
 * the football-data id, so running it again never duplicates records.
 */
final class ImportCompetition
{
    public function __construct(private readonly FootballDataProvider $provider) {}

    public function handle(int|string $reference, string $trigger = 'request'): Competition
    {
        $squads = $this->provider->competitionSquads($reference);

        $competition = DB::transaction(function () use ($squads) {
            $competition = $this->saveCompetition($squads->competition);
            $this->saveTeamsAndPlayers($squads);
            $this->linkTeams($competition, $squads);

            return $competition;
        });

        CompetitionImported::dispatch($competition->id, count($squads->teams), $squads->playersCount(), $trigger);

        return $competition;
    }

    private function saveCompetition(CompetitionData $data): Competition
    {
        $competition = Competition::query()->findOrNew($data->id);

        // The squads endpoint sends a reduced competition (no area / plan):
        // never overwrite what the catalog sync already stored with nulls.
        $competition->fill(array_filter(CompetitionAttributes::from($data), fn ($value) => $value !== null));
        $competition->teams_synced_at = now();
        $competition->save();

        return $competition;
    }

    /**
     * Upsert on the composite key instead of sync(): sync() reads the links,
     * then deletes and inserts, so two imports of the same league running at
     * the same time (a request and the queued job) could collide on the key.
     */
    private function linkTeams(Competition $competition, CompetitionSquads $squads): void
    {
        $teamIds = array_map(fn (TeamData $team) => $team->id, $squads->teams);
        sort($teamIds);

        if ($teamIds !== []) {
            CompetitionTeam::query()->upsert(
                array_map(fn (int $teamId) => [
                    'competition_id' => $competition->id,
                    'team_id' => $teamId,
                    'season' => $squads->season,
                ], $teamIds),
                ['competition_id', 'team_id'],
                ['season'],
            );
        }

        // Teams that are no longer in the competition (relegated) lose the link.
        CompetitionTeam::query()
            ->where('competition_id', $competition->id)
            ->whereNotIn('team_id', $teamIds)
            ->delete();
    }

    /**
     * Rows are written ordered by id so concurrent imports lock them in the
     * same order (no deadlocks).
     */
    private function saveTeamsAndPlayers(CompetitionSquads $squads): void
    {
        $teams = array_map(fn (TeamData $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'short_name' => $team->shortName,
            'tla' => $team->tla,
            'crest' => $team->crest,
            'address' => $team->address,
            'website' => $team->website,
            'founded' => $team->founded,
            'club_colors' => $team->clubColors,
            'venue' => $team->venue,
            'area_name' => $team->areaName,
            'coach_name' => $team->coachName,
            'provider_updated_at' => $team->lastUpdated?->format(DateTimeInterface::ATOM),
        ], $squads->teams);

        $players = [];
        foreach ($squads->teams as $team) {
            foreach ($team->squad as $player) {
                $players[] = [
                    'id' => $player->id,
                    'team_id' => $team->id,
                    'name' => $player->name,
                    'position' => $player->position,
                    'shirt_number' => $player->shirtNumber,
                    'date_of_birth' => $player->dateOfBirth?->format('Y-m-d'),
                    'nationality' => $player->nationality,
                ];
            }
        }

        usort($teams, fn (array $a, array $b) => $a['id'] <=> $b['id']);
        usort($players, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        if ($teams !== []) {
            Team::query()->upsert($teams, ['id'], array_keys(array_diff_key($teams[0], ['id' => true])));
        }

        foreach (array_chunk($players, 500) as $chunk) {
            Player::query()->upsert($chunk, ['id'], array_keys(array_diff_key($chunk[0], ['id' => true])));
        }
    }
}
