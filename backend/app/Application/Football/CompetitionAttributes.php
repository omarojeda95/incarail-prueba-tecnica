<?php

namespace App\Application\Football;

use App\Domain\Football\Data\CompetitionData;
use DateTimeInterface;

/** Maps the provider-agnostic CompetitionData to the competitions table. */
final class CompetitionAttributes
{
    /** @return array<string, mixed> */
    public static function from(CompetitionData $data): array
    {
        return [
            'id' => $data->id,
            'name' => $data->name,
            'code' => $data->code,
            'type' => $data->type,
            'emblem' => $data->emblem,
            'plan' => $data->plan,
            'area_name' => $data->areaName,
            'area_code' => $data->areaCode,
            'area_flag' => $data->areaFlag,
            'season_start' => $data->seasonStart?->format('Y-m-d'),
            'season_end' => $data->seasonEnd?->format('Y-m-d'),
            'current_matchday' => $data->currentMatchday,
            'provider_updated_at' => $data->lastUpdated?->format(DateTimeInterface::ATOM),
        ];
    }
}
