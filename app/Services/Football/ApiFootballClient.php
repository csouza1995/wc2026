<?php

namespace App\Services\Football;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ApiFootballClient
{
    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('services.api_football.url'))
            ->withHeaders(['x-apisports-key' => config('services.api_football.key')])
            ->timeout(10)
            ->retry(2, 200);
    }

    /**
     * Single fixture, used for the live-score budgeted polling.
     *
     * @return array<string, mixed>
     */
    public function fixture(string $externalId): array
    {
        return $this->client()
            ->get('/fixtures', ['id' => $externalId])
            ->throw()
            ->json('response.0', []);
    }

    /**
     * All fixtures for the configured league/season, used once (or on a
     * low-frequency schedule) to cross-reference api-football's own
     * fixture ids against the ones we imported from football-data.org.
     *
     * @return array<int, array<string, mixed>>
     */
    public function leagueFixtures(): array
    {
        return $this->client()
            ->get('/fixtures', [
                'league' => config('services.api_football.league_id'),
                'season' => config('services.api_football.season'),
            ])
            ->throw()
            ->json('response', []);
    }
}
