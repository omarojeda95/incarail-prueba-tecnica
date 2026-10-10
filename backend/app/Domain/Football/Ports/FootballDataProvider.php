<?php

namespace App\Domain\Football\Ports;

use App\Domain\Football\Data\CompetitionData;
use App\Domain\Football\Data\CompetitionSquads;
use App\Domain\Football\Exceptions\CompetitionNotAvailable;
use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Domain\Football\Exceptions\FootballDataUnavailable;
use App\Domain\Football\Exceptions\FootballResourceNotFound;

/**
 * Port to the external football data source. The application only depends on
 * this contract; football-data.org is one adapter behind it.
 *
 * @throws FootballDataRateLimited when the request budget is exhausted
 * @throws FootballDataUnavailable when the provider cannot be reached or fails
 * @throws FootballResourceNotFound when the competition does not exist
 * @throws CompetitionNotAvailable when our plan cannot read the competition
 */
interface FootballDataProvider
{
    /** @return list<CompetitionData> */
    public function competitions(): array;

    /** Competition (by id or code) with its current teams and their squads. */
    public function competitionSquads(int|string $competition): CompetitionSquads;
}
