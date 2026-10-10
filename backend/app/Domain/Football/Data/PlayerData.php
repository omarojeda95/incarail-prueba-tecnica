<?php

namespace App\Domain\Football\Data;

use DateTimeImmutable;

final readonly class PlayerData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $position,
        public ?int $shirtNumber,
        public ?DateTimeImmutable $dateOfBirth,
        public ?string $nationality,
    ) {}
}
