<?php

namespace App\Services\Football;

use App\Enums\FixtureStatus;
use App\Enums\MatchPeriod;

class ApiFootballFixtureMapper
{
    /**
     * @var array<string, FixtureStatus>
     */
    protected const STATUSES = [
        'TBD' => FixtureStatus::Scheduled,
        'NS' => FixtureStatus::Scheduled,
        'FT' => FixtureStatus::Finished,
        'AET' => FixtureStatus::Finished,
        'PST' => FixtureStatus::Postponed,
        'CANC' => FixtureStatus::Postponed,
        'ABD' => FixtureStatus::Postponed,
        'AWD' => FixtureStatus::Postponed,
        'WO' => FixtureStatus::Postponed,
    ];

    /**
     * @var array<string, MatchPeriod>
     */
    protected const PERIODS = [
        'TBD' => MatchPeriod::Pre,
        'NS' => MatchPeriod::Pre,
        '1H' => MatchPeriod::FirstHalf,
        'HT' => MatchPeriod::HalfTime,
        '2H' => MatchPeriod::SecondHalf,
        'BT' => MatchPeriod::HalfTime,
        'P' => MatchPeriod::Penalties,
        'PEN' => MatchPeriod::Penalties,
        'FT' => MatchPeriod::FullTime,
        'AET' => MatchPeriod::FullTime,
        // SUSP/INT (suspended/interrupted) fall back to the generic pause bucket below.
    ];

    /**
     * Map a raw api-football.com fixture into the live-score subset of
     * Fixture attributes (score, minute, period, status). Only used
     * during the budgeted live-polling window, never for the initial
     * import — team/round/schedule data comes from football-data.org.
     *
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public static function toLiveAttributes(array $fixture): array
    {
        $short = $fixture['fixture']['status']['short'] ?? 'NS';
        $elapsed = $fixture['fixture']['status']['elapsed'] ?? null;
        $score = $fixture['score'] ?? [];

        return [
            'status' => self::STATUSES[$short] ?? FixtureStatus::Live,
            'period' => self::PERIODS[$short] ?? self::extraTimePeriod($short, $elapsed),
            'minute' => $elapsed,
            'home_score' => $fixture['goals']['home'] ?? null,
            'away_score' => $fixture['goals']['away'] ?? null,
            'home_score_et' => $score['extratime']['home'] ?? null,
            'away_score_et' => $score['extratime']['away'] ?? null,
            'home_pens' => $score['penalty']['home'] ?? null,
            'away_pens' => $score['penalty']['away'] ?? null,
        ];
    }

    private static function extraTimePeriod(string $short, ?int $elapsed): MatchPeriod
    {
        if ($short !== 'ET') {
            return MatchPeriod::HalfTime;
        }

        return $elapsed !== null && $elapsed > 105
            ? MatchPeriod::ExtraTimeSecond
            : MatchPeriod::ExtraTimeFirst;
    }
}
