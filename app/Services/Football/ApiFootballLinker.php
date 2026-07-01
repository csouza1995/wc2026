<?php

namespace App\Services\Football;

use App\Models\Fixture;
use Illuminate\Support\Carbon;

class ApiFootballLinker
{
    /**
     * Match api-football fixtures to our Fixture rows by kickoff date +
     * team names, and stamp external_id_api_football onto the match.
     *
     * Both providers use plain English national-team names, so an exact,
     * case-insensitive match on the kickoff date and both team names is
     * reliable enough here. Fixtures that don't line up (e.g. a team name
     * mismatch) are simply left unlinked — they keep working off the
     * football-data.org heartbeat alone, just without the API-Football
     * boost during critical windows.
     *
     * @param  array<int, array<string, mixed>>  $apiFootballFixtures
     */
    public function link(array $apiFootballFixtures): int
    {
        $linked = 0;

        foreach ($apiFootballFixtures as $raw) {
            $externalId = (string) ($raw['fixture']['id'] ?? '');
            $date = $raw['fixture']['date'] ?? null;
            $homeName = $raw['teams']['home']['name'] ?? null;
            $awayName = $raw['teams']['away']['name'] ?? null;

            if ($externalId === '' || ! $date || ! $homeName || ! $awayName) {
                continue;
            }

            $fixture = Fixture::query()
                ->whereDate('kickoff_at', Carbon::parse($date)->toDateString())
                ->whereHas('homeTeam', fn ($query) => $query->whereRaw('lower(name) = ?', [strtolower($homeName)]))
                ->whereHas('awayTeam', fn ($query) => $query->whereRaw('lower(name) = ?', [strtolower($awayName)]))
                ->first();

            if ($fixture && $fixture->external_id_api_football !== $externalId) {
                $fixture->update(['external_id_api_football' => $externalId]);
                $linked++;
            }
        }

        return $linked;
    }
}
