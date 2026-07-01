<?php

namespace App\Console\Commands\Fixtures;

use App\Services\Football\FixtureSyncer;
use App\Services\Football\FootballDataClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fixtures:import')]
#[Description('One-off import of teams, groups and the full fixture list from football-data.org')]
class ImportFixtures extends Command
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

        $count = $syncer->sync($matches);

        $this->info("Imported {$count} fixtures.");

        return self::SUCCESS;
    }
}
