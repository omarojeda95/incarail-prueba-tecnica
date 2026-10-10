<?php

namespace App\Domain\Football\Data;

use DateTimeImmutable;

/** A competition as described by the football provider, independent of its wire format. */
final readonly class CompetitionData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $code,
        public ?string $type,
        public ?string $emblem,
        public ?string $plan,
        public ?string $areaName,
        public ?string $areaCode,
        public ?string $areaFlag,
        public ?DateTimeImmutable $seasonStart,
        public ?DateTimeImmutable $seasonEnd,
        public ?int $currentMatchday,
        public ?DateTimeImmutable $lastUpdated,
    ) {}
}
