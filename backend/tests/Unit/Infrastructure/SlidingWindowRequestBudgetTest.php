<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Infrastructure\FootballData\SlidingWindowRequestBudget;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SlidingWindowRequestBudgetTest extends TestCase
{
    private SlidingWindowRequestBudget $budget;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 12:00:00');
        $this->budget = new SlidingWindowRequestBudget(new Repository(new ArrayStore), maxRequests: 10, windowSeconds: 60);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_allows_up_to_the_maximum_requests_per_window(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->budget->acquire();
        }

        $this->assertSame(0, $this->budget->remaining());
    }

    #[Test]
    public function it_rejects_the_request_over_the_limit_with_the_seconds_to_wait(): void
    {
        $this->spend(10);
        Carbon::setTestNow(now()->addSeconds(15));

        try {
            $this->budget->acquire();
            $this->fail('The 11th request in the window should be rejected.');
        } catch (FootballDataRateLimited $e) {
            $this->assertSame(45, $e->retryAfter);
        }
    }

    #[Test]
    public function the_window_slides_instead_of_resetting_all_at_once(): void
    {
        $this->spend(5);
        Carbon::setTestNow(now()->addSeconds(30));
        $this->spend(5);

        Carbon::setTestNow(now()->addSeconds(31)); // first five are now older than 60s

        $this->assertSame(5, $this->budget->remaining());
        $this->spend(5);
        $this->assertSame(0, $this->budget->remaining());
    }

    #[Test]
    public function it_can_be_exhausted_when_the_provider_says_so(): void
    {
        $this->budget->exhaustFor(20);

        $this->assertSame(0, $this->budget->remaining());
        $this->assertSame(20, $this->budget->retryAfter());

        Carbon::setTestNow(now()->addSeconds(21));
        $this->budget->acquire();
        $this->assertSame(9, $this->budget->remaining());
    }

    #[Test]
    public function retry_after_is_zero_when_there_is_budget_left(): void
    {
        $this->spend(3);

        $this->assertSame(0, $this->budget->retryAfter());
        $this->assertSame(7, $this->budget->remaining());
    }

    private function spend(int $requests): void
    {
        for ($i = 0; $i < $requests; $i++) {
            $this->budget->acquire();
        }
    }
}
