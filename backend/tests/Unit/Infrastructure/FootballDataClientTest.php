<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Football\Exceptions\CompetitionNotAvailable;
use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Domain\Football\Exceptions\FootballDataUnavailable;
use App\Domain\Football\Exceptions\FootballResourceNotFound;
use App\Infrastructure\FootballData\FootballDataClient;
use App\Infrastructure\FootballData\RequestBudget;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesFootballData;
use Tests\TestCase;

class FootballDataClientTest extends TestCase
{
    use FakesFootballData;

    protected function setUp(): void
    {
        parent::setUp();
        config(['football.token' => 'test-token', 'football.base_url' => 'https://football.test/v4']);
    }

    #[Test]
    public function it_maps_the_competition_list_and_authenticates_with_the_token(): void
    {
        Http::fake(['football.test/v4/competitions' => Http::response($this->competitionsPayload())]);

        $competitions = $this->client()->competitions();

        $this->assertCount(2, $competitions);
        $this->assertSame(2021, $competitions[0]->id);
        $this->assertSame('PL', $competitions[0]->code);
        $this->assertSame('England', $competitions[0]->areaName);
        $this->assertSame('TIER_ONE', $competitions[0]->plan);
        $this->assertSame('2026-08-15', $competitions[0]->seasonStart?->format('Y-m-d'));
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Auth-Token', 'test-token'));
    }

    #[Test]
    public function it_maps_a_competition_with_its_teams_and_squads(): void
    {
        Http::fake(['football.test/v4/competitions/PL/teams' => Http::response($this->competitionTeamsPayload())]);

        $result = $this->client()->competitionSquads('PL');

        $this->assertSame(2021, $result->competition->id);
        $this->assertSame('2026', $result->season);
        $this->assertCount(2, $result->teams);
        $this->assertSame('Arsenal FC', $result->teams[0]->name);
        $this->assertSame('Mikel Arteta', $result->teams[0]->coachName);
        $this->assertSame('David Raya', $result->teams[0]->squad[0]->name);
        $this->assertSame('Goalkeeper', $result->teams[0]->squad[0]->position);
        $this->assertSame(22, $result->teams[0]->squad[0]->shirtNumber);
        $this->assertNull($result->teams[0]->squad[1]->shirtNumber);
        $this->assertSame(3, $result->playersCount());
    }

    #[Test]
    public function it_does_not_call_the_provider_when_the_budget_is_exhausted(): void
    {
        Http::fake();
        $this->app->make(RequestBudget::class)->exhaustFor(30);

        try {
            $this->client()->competitions();
            $this->fail('Expected the rate limit to be enforced locally.');
        } catch (FootballDataRateLimited $e) {
            $this->assertSame(30, $e->retryAfter);
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function it_trusts_the_provider_headers_when_they_report_no_requests_left(): void
    {
        Http::fake(['*' => Http::response($this->competitionsPayload(), 200, [
            'X-Requests-Available-Minute' => '0',
            'X-RequestCounter-Reset' => '42',
        ])]);

        $this->client()->competitions();

        $this->assertSame(42, $this->app->make(RequestBudget::class)->retryAfter());
    }

    #[Test]
    public function a_429_from_the_provider_becomes_a_rate_limit_and_blocks_the_budget(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too many requests'], 429, ['X-RequestCounter-Reset' => '17'])]);

        try {
            $this->client()->competitions();
            $this->fail('Expected FootballDataRateLimited.');
        } catch (FootballDataRateLimited $e) {
            $this->assertSame(17, $e->retryAfter);
        }

        $this->assertSame(0, $this->app->make(RequestBudget::class)->remaining());
    }

    /** @return array<string, array{int, class-string}> */
    public static function providerErrors(): array
    {
        return [
            'not found' => [404, FootballResourceNotFound::class],
            'bad request' => [400, FootballResourceNotFound::class],
            'not in our plan' => [403, CompetitionNotAvailable::class],
            'server error' => [500, FootballDataUnavailable::class],
            'invalid token' => [401, FootballDataUnavailable::class],
        ];
    }

    /** @param  class-string<\Throwable>  $exception */
    #[Test]
    #[DataProvider('providerErrors')]
    public function it_translates_provider_errors_into_domain_exceptions(int $status, string $exception): void
    {
        Http::fake(['*' => Http::response(['message' => 'error'], $status)]);

        $this->expectException($exception);

        $this->client()->competitionSquads(9999);
    }

    #[Test]
    public function a_connection_failure_is_reported_as_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        try {
            $this->client()->competitions();
            $this->fail('Expected FootballDataUnavailable.');
        } catch (FootballDataUnavailable $e) {
            $this->assertTrue($e->connectionFailed);
        }
    }

    #[Test]
    public function it_refuses_to_run_without_a_token(): void
    {
        config(['football.token' => null]);
        Http::fake();

        $this->expectException(FootballDataUnavailable::class);

        try {
            $this->client()->competitions();
        } finally {
            Http::assertNothingSent();
        }
    }

    private function client(): FootballDataClient
    {
        return $this->app->make(FootballDataClient::class);
    }
}
