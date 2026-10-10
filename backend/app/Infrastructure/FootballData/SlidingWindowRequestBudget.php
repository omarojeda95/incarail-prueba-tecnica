<?php

namespace App\Infrastructure\FootballData;

use App\Domain\Football\Exceptions\FootballDataRateLimited;
use Illuminate\Contracts\Cache\Repository;

/**
 * Sliding-window log of the requests sent in the last N seconds, kept in a
 * shared cache (Redis in Docker) behind an atomic lock, so the web workers,
 * the queue worker and the scheduler all spend the same budget.
 *
 * A fixed window would allow 2x the limit around the window boundary
 * (10 at 00:59 + 10 at 01:00); the sliding log never sends more than
 * $maxRequests in any $windowSeconds span.
 */
final class SlidingWindowRequestBudget implements RequestBudget
{
    public function __construct(
        private readonly Repository $cache,
        private readonly int $maxRequests,
        private readonly int $windowSeconds,
        private readonly string $key = 'football-data:budget',
    ) {}

    public function acquire(): void
    {
        $this->cache->lock("{$this->key}:lock", 5)->block(5, function () {
            $retryAfter = $this->computeRetryAfter($hits = $this->recentHits());

            if ($retryAfter > 0) {
                throw new FootballDataRateLimited($retryAfter);
            }

            $hits[] = now()->getTimestampMs();
            $this->cache->put("{$this->key}:hits", $hits, $this->windowSeconds + 5);
        });
    }

    public function remaining(): int
    {
        if ($this->blockedFor() > 0) {
            return 0;
        }

        return max(0, $this->maxRequests - count($this->recentHits()));
    }

    public function retryAfter(): int
    {
        return $this->computeRetryAfter($this->recentHits());
    }

    public function exhaustFor(int $seconds): void
    {
        $until = now()->addSeconds(max(1, $seconds))->getTimestamp();
        $this->cache->put("{$this->key}:blocked-until", $until, max(1, $seconds) + 5);
    }

    /** @param  list<int>  $hits */
    private function computeRetryAfter(array $hits): int
    {
        $blocked = $this->blockedFor();

        if (count($hits) < $this->maxRequests) {
            return $blocked;
        }

        $oldestExpiresAt = intdiv($hits[count($hits) - $this->maxRequests], 1000) + $this->windowSeconds;

        return max($blocked, $oldestExpiresAt - now()->getTimestamp(), 1);
    }

    private function blockedFor(): int
    {
        $until = (int) $this->cache->get("{$this->key}:blocked-until", 0);

        return max(0, $until - now()->getTimestamp());
    }

    /** @return list<int> timestamps (ms) of the requests inside the window */
    private function recentHits(): array
    {
        $since = now()->subSeconds($this->windowSeconds)->getTimestampMs();

        return array_values(array_filter(
            $this->cache->get("{$this->key}:hits", []),
            fn (int $hit) => $hit > $since,
        ));
    }
}
