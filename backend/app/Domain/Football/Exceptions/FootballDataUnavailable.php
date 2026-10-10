<?php

namespace App\Domain\Football\Exceptions;

use Throwable;

/** The provider is down, timed out, misconfigured or answered something unexpected. */
final class FootballDataUnavailable extends FootballDataException
{
    public function __construct(
        string $message,
        public readonly bool $connectionFailed = false,
        ?Throwable $previous = null,
        public readonly bool $notConfigured = false,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('FOOTBALL_DATA_TOKEN is not configured.', notConfigured: true);
    }
}
