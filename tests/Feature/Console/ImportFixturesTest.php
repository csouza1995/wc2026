<?php

use App\Enums\FixtureRound;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

function fakeFootballDataMatches(): array
{
    return [
        [
            'id' => 1001,
            'stage' => 'GROUP_STAGE',
            'group' => 'GROUP_A',
            'matchday' => 1,
            'utcDate' => '2026-06-11T18:00:00Z',
            'status' => 'FINISHED',
            'score' => [
                'fullTime' => ['home' => 2, 'away' => 1],
                'extraTime' => ['home' => null, 'away' => null],
                'penalties' => ['home' => null, 'away' => null],
            ],
            'homeTeam' => ['id' => 1, 'name' => 'Brazil', 'tla' => 'BRA', 'crest' => 'https://crests.example/bra.svg'],
            'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN', 'crest' => 'https://crests.example/can.svg'],
        ],
        [
            'id' => 2001,
            'stage' => 'LAST_32',
            'group' => null,
            'matchday' => null,
            'utcDate' => '2026-06-30T18:00:00Z',
            'status' => 'SCHEDULED',
            'score' => [
                'fullTime' => ['home' => null, 'away' => null],
                'extraTime' => ['home' => null, 'away' => null],
                'penalties' => ['home' => null, 'away' => null],
            ],
            'homeTeam' => ['id' => 1, 'name' => 'Brazil', 'tla' => 'BRA', 'crest' => 'https://crests.example/bra.svg'],
            'awayTeam' => ['id' => null, 'name' => 'Runner-up Group B', 'tla' => null, 'crest' => null],
        ],
    ];
}

test('imports teams, groups and fixtures from football-data.org', function () {
    Http::fake([
        'api.football-data.org/*' => Http::response(['matches' => fakeFootballDataMatches()]),
    ]);

    $this->artisan('fixtures:import')->assertSuccessful();

    expect(Team::count())->toBe(2)
        ->and(Group::count())->toBe(1)
        ->and(Fixture::count())->toBe(2);

    $groupFixture = Fixture::where('external_id_football_data', '1001')->sole();
    expect($groupFixture->round)->toBe(FixtureRound::Group)
        ->and($groupFixture->status)->toBe(FixtureStatus::Finished)
        ->and($groupFixture->home_score)->toBe(2)
        ->and($groupFixture->group->name)->toBe('A');

    $knockoutFixture = Fixture::where('external_id_football_data', '2001')->sole();
    expect($knockoutFixture->round)->toBe(FixtureRound::RoundOf32)
        ->and($knockoutFixture->away_team_id)->toBeNull()
        ->and($knockoutFixture->away_placeholder)->toBe('Runner-up Group B');
});

test('running the import twice does not duplicate records', function () {
    Http::fake([
        'api.football-data.org/*' => Http::response(['matches' => fakeFootballDataMatches()]),
    ]);

    $this->artisan('fixtures:import')->assertSuccessful();
    $this->artisan('fixtures:import')->assertSuccessful();

    expect(Team::count())->toBe(2)
        ->and(Group::count())->toBe(1)
        ->and(Fixture::count())->toBe(2);
});
