<?php

use App\Enums\DataSource;
use App\Enums\MatchPeriod;
use App\Models\Fixture;
use App\Services\Football\LiveScoreBudget;

test('uses api-football when the fixture is in a critical window, even right after a recent ping', function () {
    $budget = new LiveScoreBudget(dailyQuota: 100);

    $fixture = Fixture::factory()->create([
        'minute' => 40,
        'period' => MatchPeriod::FirstHalf,
        'last_polled_source' => DataSource::ApiFootball,
        'last_polled_at' => now(),
    ]);

    expect($budget->shouldUseApiFootball($fixture, liveMatchesToday: 1))->toBeTrue();
});

test('skips api-football in a normal window when it was recently polled', function () {
    $budget = new LiveScoreBudget(dailyQuota: 100);

    $fixture = Fixture::factory()->create([
        'minute' => 10,
        'period' => MatchPeriod::FirstHalf,
        'last_polled_source' => DataSource::ApiFootball,
        'last_polled_at' => now(),
    ]);

    expect($budget->shouldUseApiFootball($fixture, liveMatchesToday: 1))->toBeFalse();
});

test('falls back to api-football when it has not been polled in a while, regardless of window', function () {
    $budget = new LiveScoreBudget(dailyQuota: 100);

    $fixture = Fixture::factory()->create([
        'minute' => 10,
        'period' => MatchPeriod::FirstHalf,
        'last_polled_source' => DataSource::ApiFootball,
        'last_polled_at' => now()->subMinutes(15),
    ]);

    expect($budget->shouldUseApiFootball($fixture, liveMatchesToday: 1))->toBeTrue();
});

test('never uses api-football once the daily quota is exhausted', function () {
    $budget = new LiveScoreBudget(dailyQuota: 1);
    $budget->recordUsage(DataSource::ApiFootball);

    $fixture = Fixture::factory()->create([
        'minute' => 40,
        'period' => MatchPeriod::FirstHalf,
    ]);

    expect($budget->shouldUseApiFootball($fixture, liveMatchesToday: 1))->toBeFalse();
});

test('splits the remaining quota across all live matches today', function () {
    $budget = new LiveScoreBudget(dailyQuota: 4);
    $budget->recordUsage(DataSource::ApiFootball);
    $budget->recordUsage(DataSource::ApiFootball);
    $budget->recordUsage(DataSource::ApiFootball);
    // 1 request left in the quota, but 2 matches live today -> 0 per match.

    $fixture = Fixture::factory()->create([
        'minute' => 40,
        'period' => MatchPeriod::FirstHalf,
    ]);

    expect($budget->shouldUseApiFootball($fixture, liveMatchesToday: 2))->toBeFalse();
});
