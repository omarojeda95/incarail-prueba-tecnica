<?php

namespace App\Domain\Football\Exceptions;

/** The competition exists but is not included in our football-data plan. */
final class CompetitionNotAvailable extends FootballDataException {}
