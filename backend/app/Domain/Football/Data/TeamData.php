<?php

namespace App\Domain\Football\Data;

use DateTimeImmutable;

final readonly class TeamData
{
    /**
     * @param  list<PlayerData>  $squad
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $shortName,
        public ?string $tla,
        public ?string $crest,
        public ?string $address,
        public ?string $website,
        public ?int $founded,
        public ?string $clubColors,
        public ?string $venue,
        public ?string $areaName,
        public ?string $coachName,
        public ?DateTimeImmutable $lastUpdated,
        public array $squad = [],
    ) {}
}
