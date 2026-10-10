<?php

namespace App\Application\Football\Queries;

use DateTimeInterface;

/** A read-model answer plus where it came from, so the API can tell the client. */
final readonly class QueryResult
{
    public function __construct(
        public mixed $data,
        public bool $stale = false,
        public ?DateTimeInterface $syncedAt = null,
        public string $source = 'database',
        public ?int $retryAfter = null,
    ) {}

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return array_filter([
            'source' => $this->source,
            'stale' => $this->stale,
            'synced_at' => $this->syncedAt?->format(DATE_ATOM),
            'retry_after' => $this->retryAfter,
        ], fn ($value) => $value !== null);
    }
}
