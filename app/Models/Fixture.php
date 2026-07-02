<?php

namespace App\Models;

use App\Enums\DataSource;
use App\Enums\FixtureRound;
use App\Enums\FixtureStatus;
use App\Enums\MatchPeriod;
use Carbon\CarbonImmutable;
use Database\Factories\FixtureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property FixtureRound $round
 * @property int|null $group_id
 * @property int|null $matchday
 * @property int|null $home_team_id
 * @property int|null $away_team_id
 * @property string|null $home_placeholder
 * @property string|null $away_placeholder
 * @property CarbonImmutable $kickoff_at
 * @property string|null $venue
 * @property FixtureStatus $status
 * @property int|null $home_score
 * @property int|null $away_score
 * @property int|null $home_score_et
 * @property int|null $away_score_et
 * @property int|null $home_pens
 * @property int|null $away_pens
 * @property int|null $minute
 * @property MatchPeriod|null $period
 * @property string|null $external_id_football_data
 * @property string|null $external_id_api_football
 * @property CarbonImmutable|null $last_polled_at
 * @property DataSource|null $last_polled_source
 */
#[Fillable([
    'round', 'group_id', 'matchday',
    'home_team_id', 'away_team_id', 'home_placeholder', 'away_placeholder',
    'kickoff_at', 'venue', 'status',
    'home_score', 'away_score', 'home_score_et', 'away_score_et', 'home_pens', 'away_pens',
    'minute', 'period',
    'external_id_football_data', 'external_id_api_football', 'last_polled_at', 'last_polled_source',
])]
class Fixture extends Model
{
    /** @use HasFactory<FixtureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => FixtureRound::class,
            'status' => FixtureStatus::class,
            'period' => MatchPeriod::class,
            'last_polled_source' => DataSource::class,
            'kickoff_at' => 'datetime',
            'last_polled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function isLive(): bool
    {
        return $this->status === FixtureStatus::Live;
    }

    /**
     * Kickoff time converted from UTC (storage) to the app's display
     * timezone, for anything shown to users (formatting, day grouping).
     */
    public function kickoffAtLocal(): CarbonImmutable
    {
        return $this->kickoff_at->setTimezone(config('app.display_timezone'));
    }

    /**
     * The team that advances, for a decided knockout fixture. Checked in
     * order: regular time, extra time, penalties. Null if not finished or
     * (shouldn't happen for a knockout match, but guarded) still level.
     */
    public function winner(): ?Team
    {
        if ($this->status !== FixtureStatus::Finished) {
            return null;
        }

        return match (true) {
            $this->home_score !== $this->away_score => $this->home_score > $this->away_score ? $this->homeTeam : $this->awayTeam,
            $this->home_score_et !== null && $this->home_score_et !== $this->away_score_et => $this->home_score_et > $this->away_score_et ? $this->homeTeam : $this->awayTeam,
            $this->home_pens !== null && $this->home_pens !== $this->away_pens => $this->home_pens > $this->away_pens ? $this->homeTeam : $this->awayTeam,
            default => null,
        };
    }
}
