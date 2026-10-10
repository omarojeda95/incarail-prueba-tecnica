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
    ) {
        parent::__construct($message, 0, $previous);
    }
}
