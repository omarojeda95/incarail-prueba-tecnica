<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Link between a competition and a team for a season (composite key). */
#[Fillable(['competition_id', 'team_id', 'season'])]
class CompetitionTeam extends Pivot
{
    protected $table = 'competition_team';
}
