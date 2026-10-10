<?php

use Illuminate\Support\Facades\Schedule;

// Keep the local copy fresh in the background, inside the request budget.
Schedule::command('football:sync-competitions')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('football:refresh-stale')->hourly()->withoutOverlapping();
