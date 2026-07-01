<?php

namespace App\Services\Football;

use App\Enums\FixtureRound;
use App\Enums\FixtureStatus;
use Illuminate\Support\Carbon;

class FootballDataMatchMapper
{
    /**
     * @var array<string, FixtureRound>
     */
    protected const ROUNDS = [
        'GROUP_STAGE' => FixtureRound::Group,
        'LAST_32' => FixtureRound::RoundOf32,
        'LAST_16' => FixtureRound::RoundOf16,
        'QUARTER_FINALS' => FixtureRound::QuarterFinal,
        'SEMI_FINALS' => FixtureRound::SemiFinal,
        'THIRD_PLACE' => FixtureRound::ThirdPlace,
        'FINAL' => FixtureRound::Final,
    ];

    /**
     * @var array<string, FixtureStatus>
     */
    protected const STATUSES = [
        'SCHEDULED' => FixtureStatus::Scheduled,
        'TIMED' => FixtureStatus::Scheduled,
        'IN_PLAY' => FixtureStatus::Live,
        'PAUSED' => FixtureStatus::Live,
        'FINISHED' => FixtureStatus::Finished,
        'AWARDED' => FixtureStatus::Finished,
        'SUSPENDED' => FixtureStatus::Postponed,
        'POSTPONED' => FixtureStatus::Postponed,
        'CANCELLED' => FixtureStatus::Postponed,
    ];

    /**
     * Map a raw football-data.org match into Fixture attributes.
     *
     * Team IDs are intentionally left out here — the caller resolves
     * home/away team ids from the `external_id_football_data` on Team
     * or from group/placeholder membership, since this mapper has no
     * database access.
     *
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    public static function toFixtureAttributes(array $match): array
    {
        $score = $match['score'] ?? [];

        return [
            'external_id_football_data' => (string) $match['id'],
            'round' => self::ROUNDS[$match['stage'] ?? ''] ?? FixtureRound::Group,
            'matchday' => $match['matchday'] ?? null,
            'kickoff_at' => Carbon::parse($match['utcDate']),
            'status' => self::STATUSES[$match['status'] ?? ''] ?? FixtureStatus::Scheduled,
            'home_score' => $score['fullTime']['home'] ?? null,
            'away_score' => $score['fullTime']['away'] ?? null,
            'home_score_et' => $score['extraTime']['home'] ?? null,
            'away_score_et' => $score['extraTime']['away'] ?? null,
            'home_pens' => $score['penalties']['home'] ?? null,
            'away_pens' => $score['penalties']['away'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $match
     */
    public static function groupName(array $match): ?string
    {
        $group = $match['group'] ?? null;

        return is_string($group) ? str_replace('GROUP_', '', $group) : null;
    }
}
