<?php

namespace App\Console\Commands;

use App\Enums\DataSource;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Services\Football\ApiFootballClient;
use App\Services\Football\ApiFootballFixtureMapper;
use App\Services\Football\FootballDataClient;
use App\Services\Football\FootballDataMatchMapper;
use App\Services\Football\LiveScoreBudget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('matches:watch-live')]
#[Description('Poll live fixtures every ~10s: football-data.org as the heartbeat, api-football spent on critical windows only')]
class WatchLiveMatches extends Command
{
    /**
     * Safety window around kickoff so we keep polling through stoppage
     * time, extra time and penalties even without a live minute yet.
     */
    protected const LIVE_WINDOW_HOURS = 3;

    /**
     * Execute the console command.
     */
    public function handle(FootballDataClient $footballData, ApiFootballClient $apiFootball, LiveScoreBudget $budget): int
    {
        $fixtures = Fixture::query()
            ->where('status', '!=', FixtureStatus::Finished)
            ->whereNotNull('external_id_football_data')
            ->where('kickoff_at', '<=', now())
            ->where('kickoff_at', '>=', now()->subHours(self::LIVE_WINDOW_HOURS))
            ->get();

        if ($fixtures->isEmpty()) {
            return self::SUCCESS;
        }

        $liveCount = $fixtures->count();

        foreach ($fixtures as $fixture) {
            $this->pingFootballData($fixture, $footballData);

            if ($fixture->external_id_api_football && $budget->shouldUseApiFootball($fixture, $liveCount)) {
                $this->pingApiFootball($fixture, $apiFootball, $budget);
            }
        }

        return self::SUCCESS;
    }

    private function pingFootballData(Fixture $fixture, FootballDataClient $client): void
    {
        try {
            $raw = $client->match($fixture->external_id_football_data);

            $fixture->fill(FootballDataMatchMapper::toFixtureAttributes($raw));
            $fixture->last_polled_at = now();
            $fixture->last_polled_source = DataSource::FootballData;
            $fixture->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function pingApiFootball(Fixture $fixture, ApiFootballClient $client, LiveScoreBudget $budget): void
    {
        try {
            $raw = $client->fixture($fixture->external_id_api_football);

            if ($raw === []) {
                return;
            }

            $fixture->fill(ApiFootballFixtureMapper::toLiveAttributes($raw));
            $fixture->last_polled_at = now();
            $fixture->last_polled_source = DataSource::ApiFootball;
            $fixture->save();

            $budget->recordUsage(DataSource::ApiFootball);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
