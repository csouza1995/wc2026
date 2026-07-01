<?php

namespace App\Console\Commands\Fixtures;

use App\Enums\DataSource;
use App\Services\Football\ApiFootballClient;
use App\Services\Football\ApiFootballLinker;
use App\Services\Football\LiveScoreBudget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fixtures:link-api-football')]
#[Description('Cross-reference api-football fixture ids onto our Fixture rows (costs 1 API-Football request)')]
class LinkApiFootball extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ApiFootballClient $client, ApiFootballLinker $linker, LiveScoreBudget $budget): int
    {
        $fixtures = $client->leagueFixtures();
        $budget->recordUsage(DataSource::ApiFootball);

        if ($fixtures === []) {
            $this->error('api-football returned no fixtures for the configured league/season.');

            return self::FAILURE;
        }

        $linked = $linker->link($fixtures);

        $this->info("Linked {$linked} fixtures to their api-football id.");

        return self::SUCCESS;
    }
}
