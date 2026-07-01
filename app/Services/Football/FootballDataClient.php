<?php

namespace App\Services\Football;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FootballDataClient
{
    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('services.football_data.url'))
            ->withHeaders(['X-Auth-Token' => config('services.football_data.key')])
            ->timeout(10)
            ->retry(2, 200);
    }

    /**
     * All matches (group stage + knockout) for the configured competition.
     *
     * @return array<int, array<string, mixed>>
     */
    public function competitionMatches(): array
    {
        $competition = config('services.football_data.competition_code');

        return $this->client()
            ->get("/competitions/{$competition}/matches")
            ->throw()
            ->json('matches', []);
    }

    /**
     * Single match, used for the live-score heartbeat.
     *
     * @return array<string, mixed>
     */
    public function match(string $externalId): array
    {
        return $this->client()
            ->get("/matches/{$externalId}")
            ->throw()
            ->json();
    }
}
