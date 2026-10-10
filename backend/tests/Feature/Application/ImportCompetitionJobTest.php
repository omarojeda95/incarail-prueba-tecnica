<?php

namespace Tests\Feature\Application;

use App\Application\Football\ImportCompetition;
use App\Domain\Football\Data\CompetitionSquads;
use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Domain\Football\Exceptions\FootballDataUnavailable;
use App\Domain\Football\Ports\FootballDataProvider;
use App\Jobs\ImportCompetitionJob;
use App\Models\Competition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fakes\InMemoryFootballDataProvider as Fake;
use Tests\TestCase;

class ImportCompetitionJobTest extends TestCase
{
    use RefreshDatabase;

    private Fake $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new Fake;
        $this->app->instance(FootballDataProvider::class, $this->provider);
    }

    #[Test]
    public function it_imports_the_competition(): void
    {
        $this->provider->withSquads(new CompetitionSquads(Fake::competition(), '2026', []));

        ImportCompetitionJob::dispatchSync('PL');

        $this->assertTrue(Competition::findOrFail(2021)->isImported());
    }

    #[Test]
    public function when_the_budget_is_spent_it_goes_back_to_the_queue_until_a_slot_frees(): void
    {
        $this->provider->failWith(new FootballDataRateLimited(30));
        $job = (new ImportCompetitionJob('PL'))->withFakeQueueInteractions();

        $job->handle($this->app->make(ImportCompetition::class));

        $job->assertReleased();
        $this->assertGreaterThanOrEqual(31, $job->job->releaseDelay);
        $this->assertLessThanOrEqual(35, $job->job->releaseDelay);
    }

    #[Test]
    public function when_football_data_is_down_it_retries_a_minute_later(): void
    {
        $this->provider->failWith(new FootballDataUnavailable('down', connectionFailed: true));
        $job = (new ImportCompetitionJob('PL'))->withFakeQueueInteractions();

        $job->handle($this->app->make(ImportCompetition::class));

        $job->assertReleased(delay: 60);
    }

    #[Test]
    public function the_same_competition_is_never_queued_twice(): void
    {
        Queue::fake();

        ImportCompetitionJob::dispatch('PL');
        ImportCompetitionJob::dispatch('pl');
        ImportCompetitionJob::dispatch(2014);

        Queue::assertPushed(ImportCompetitionJob::class, 2);
    }
}
