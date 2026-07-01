<?php

namespace App\Services\Standings;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class StandingsCalculator
{
    /**
     * Group table ordered by Pts, then goal difference, then goals scored.
     *
     * Simplification: FIFA's official tie-break also considers head-to-head
     * result, disciplinary points and a drawing of lots for edge cases we
     * don't replicate here — see plan notes. Good enough for display; the
     * knockout bracket itself is never derived from this ranking, it's
     * mirrored from football-data.org directly.
     *
     * @return Collection<int, StandingRow>
     */
    public function forGroup(Group $group): Collection
    {
        $finished = $group->fixtures()
            ->with(['homeTeam', 'awayTeam'])
            ->where('status', FixtureStatus::Finished)
            ->get();

        return $group->teams
            ->map(fn (Team $team) => $this->rowFor($team, $finished))
            ->sort(fn (StandingRow $a, StandingRow $b) => [$b->points(), $b->goalDifference(), $b->goalsFor]
                <=> [$a->points(), $a->goalDifference(), $a->goalsFor])
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Fixture>  $groupFixtures
     */
    private function rowFor(Team $team, EloquentCollection $groupFixtures): StandingRow
    {
        $played = $won = $drawn = $lost = $goalsFor = $goalsAgainst = 0;

        foreach ($groupFixtures as $fixture) {
            if ($fixture->home_team_id !== $team->id && $fixture->away_team_id !== $team->id) {
                continue;
            }

            $isHome = $fixture->home_team_id === $team->id;
            $scored = $isHome ? $fixture->home_score : $fixture->away_score;
            $conceded = $isHome ? $fixture->away_score : $fixture->home_score;

            $played++;
            $goalsFor += $scored;
            $goalsAgainst += $conceded;

            match (true) {
                $scored > $conceded => $won++,
                $scored === $conceded => $drawn++,
                default => $lost++,
            };
        }

        return new StandingRow(
            team: $team,
            played: $played,
            won: $won,
            drawn: $drawn,
            lost: $lost,
            goalsFor: $goalsFor,
            goalsAgainst: $goalsAgainst,
            lastFive: $this->lastFive($team),
        );
    }

    /**
     * @return array<int, 'W'|'D'|'L'>
     */
    private function lastFive(Team $team): array
    {
        return Fixture::query()
            ->where('status', FixtureStatus::Finished)
            ->where(fn ($query) => $query->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
            ->orderByDesc('kickoff_at')
            ->limit(5)
            ->get()
            ->map(function (Fixture $fixture) use ($team) {
                $isHome = $fixture->home_team_id === $team->id;
                $scored = $isHome ? $fixture->home_score : $fixture->away_score;
                $conceded = $isHome ? $fixture->away_score : $fixture->home_score;

                return match (true) {
                    $scored > $conceded => 'W',
                    $scored === $conceded => 'D',
                    default => 'L',
                };
            })
            ->all();
    }
}
