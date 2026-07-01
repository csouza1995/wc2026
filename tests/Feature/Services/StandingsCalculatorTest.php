<?php

use App\Enums\FixtureRound;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\Team;
use App\Services\Standings\StandingsCalculator;

test('ranks teams by points, then goal difference, then goals scored', function () {
    $group = Group::factory()->create();
    [$brazil, $argentina, $chile] = Team::factory()->count(3)->create();
    $group->teams()->attach([$brazil->id, $argentina->id, $chile->id]);

    // Brazil beats Argentina 3-0 (Brazil: 3pts, +3 GD; Argentina: 0pts, -3 GD).
    Fixture::factory()->create([
        'group_id' => $group->id,
        'round' => FixtureRound::Group,
        'home_team_id' => $brazil->id,
        'away_team_id' => $argentina->id,
        'status' => FixtureStatus::Finished,
        'home_score' => 3,
        'away_score' => 0,
        'kickoff_at' => now()->subDays(2),
    ]);

    // Argentina draws Chile 1-1 (Argentina: 1pt; Chile: 1pt).
    Fixture::factory()->create([
        'group_id' => $group->id,
        'round' => FixtureRound::Group,
        'home_team_id' => $argentina->id,
        'away_team_id' => $chile->id,
        'status' => FixtureStatus::Finished,
        'home_score' => 1,
        'away_score' => 1,
        'kickoff_at' => now()->subDay(),
    ]);

    $standings = app(StandingsCalculator::class)->forGroup($group->fresh(['teams']));

    // Brazil: 3pts. Chile and Argentina tie on 1pt, but Chile's GD (0) beats
    // Argentina's GD (-3), so Chile ranks above Argentina despite Argentina
    // having played an extra match.
    expect($standings->pluck('team.id')->all())->toBe([$brazil->id, $chile->id, $argentina->id]);

    $brazilRow = $standings->firstWhere('team.id', $brazil->id);
    expect($brazilRow->points())->toBe(3)
        ->and($brazilRow->goalDifference())->toBe(3)
        ->and($brazilRow->played)->toBe(1);

    $argentinaRow = $standings->firstWhere('team.id', $argentina->id);
    expect($argentinaRow->points())->toBe(1)
        ->and($argentinaRow->goalDifference())->toBe(-3)
        ->and($argentinaRow->played)->toBe(2);
});

test('last five results are most-recent-first across all rounds', function () {
    $group = Group::factory()->create();
    [$brazil, $argentina] = Team::factory()->count(2)->create();
    $group->teams()->attach([$brazil->id, $argentina->id]);

    Fixture::factory()->create([
        'group_id' => $group->id,
        'home_team_id' => $brazil->id,
        'away_team_id' => $argentina->id,
        'status' => FixtureStatus::Finished,
        'home_score' => 1,
        'away_score' => 2,
        'kickoff_at' => now()->subDays(3),
    ]);

    Fixture::factory()->create([
        'group_id' => $group->id,
        'home_team_id' => $argentina->id,
        'away_team_id' => $brazil->id,
        'status' => FixtureStatus::Finished,
        'home_score' => 0,
        'away_score' => 0,
        'kickoff_at' => now()->subDay(),
    ]);

    $standings = app(StandingsCalculator::class)->forGroup($group->fresh(['teams']));

    $brazilRow = $standings->firstWhere('team.id', $brazil->id);
    expect($brazilRow->lastFive)->toBe(['D', 'L']);
});
