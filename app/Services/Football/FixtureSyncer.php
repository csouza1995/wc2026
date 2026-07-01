<?php

namespace App\Services\Football;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class FixtureSyncer
{
    /**
     * Upsert teams, groups and fixtures from a raw football-data.org
     * matches payload. Safe to re-run — matched by external id.
     *
     * @param  array<int, array<string, mixed>>  $matches
     */
    public function sync(array $matches): int
    {
        return DB::transaction(function () use ($matches) {
            foreach ($matches as $match) {
                $homeTeam = $this->resolveTeam($match['homeTeam'] ?? null);
                $awayTeam = $this->resolveTeam($match['awayTeam'] ?? null);
                $group = $this->resolveGroup(FootballDataMatchMapper::groupName($match), $homeTeam, $awayTeam);

                Fixture::updateOrCreate(
                    ['external_id_football_data' => (string) $match['id']],
                    [
                        ...FootballDataMatchMapper::toFixtureAttributes($match),
                        'group_id' => $group?->id,
                        'home_team_id' => $homeTeam?->id,
                        'away_team_id' => $awayTeam?->id,
                        'home_placeholder' => $homeTeam ? null : ($match['homeTeam']['name'] ?? null),
                        'away_placeholder' => $awayTeam ? null : ($match['awayTeam']['name'] ?? null),
                    ],
                );
            }

            return count($matches);
        });
    }

    /**
     * Only the fixtures football-data.org still reports as unfinished —
     * used by the periodic sync so it doesn't rewrite settled matches.
     *
     * @param  array<int, array<string, mixed>>  $matches
     */
    public function syncUnfinished(array $matches): int
    {
        $finishedExternalIds = Fixture::query()
            ->where('status', FixtureStatus::Finished)
            ->pluck('external_id_football_data')
            ->all();

        $pending = array_values(array_filter(
            $matches,
            fn (array $match) => ! in_array((string) $match['id'], $finishedExternalIds, true),
        ));

        return $this->sync($pending);
    }

    /**
     * @param  array<string, mixed>|null  $team
     */
    private function resolveTeam(?array $team): ?Team
    {
        if (! $team || ! isset($team['id'])) {
            return null;
        }

        return Team::updateOrCreate(
            ['external_id_football_data' => (string) $team['id']],
            [
                'name' => $team['name'],
                'fifa_code' => $team['tla'] ?: strtoupper(substr($team['name'], 0, 3)),
                'flag_url' => $team['crest'] ?? null,
            ],
        );
    }

    private function resolveGroup(?string $name, ?Team $homeTeam, ?Team $awayTeam): ?Group
    {
        if (! $name) {
            return null;
        }

        $group = Group::firstOrCreate(['name' => $name]);

        foreach (array_filter([$homeTeam, $awayTeam]) as $team) {
            $group->teams()->syncWithoutDetaching($team);
        }

        return $group;
    }
}
