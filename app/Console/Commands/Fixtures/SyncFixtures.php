<?php

namespace App\Console\Commands\Fixtures;

use App\Services\Football\FixtureSyncer;
use App\Services\Football\FootballDataClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fixtures:sync')]
#[Description('Refresh unfinished fixtures from football-data.org (schedule changes, knockout slots resolved)')]
class SyncFixtures extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FootballDataClient $client, FixtureSyncer $syncer): int
    {
        $matches = $client->competitionMatches();

        if ($matches === []) {
            $this->error('football-data.org returned no matches for the configured competition.');

            return self::FAILURE;
        }

        $count = $syncer->syncUnfinished($matches);

        $this->info("Synced {$count} unfinished fixtures.");

        return self::SUCCESS;
    }
}
