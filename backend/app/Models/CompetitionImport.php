<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit trail of every successful league import (written by an event listener). */
#[WithoutTimestamps]
#[Fillable(['competition_id', 'teams_count', 'players_count', 'trigger', 'imported_at'])]
class CompetitionImport extends Model
{
    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
