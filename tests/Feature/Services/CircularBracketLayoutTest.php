<?php

use App\Enums\FixtureRound;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Team;
use App\Services\Bracket\CircularBracketLayout;
use Illuminate\Support\Collection;

function teamForLayout(int $id, string $name): Team
{
    return Team::factory()->make(['id' => $id, 'name' => $name]);
}

/**
 * @return Collection<int, Fixture>
 */
function ro32FixturesForLayout(): Collection
{
    return collect(range(0, 15))->map(function (int $i) {
        $fixture = Fixture::factory()->make(['id' => 100 + $i, 'round' => FixtureRound::RoundOf32, 'status' => FixtureStatus::Scheduled]);
        $fixture->setRelation('homeTeam', teamForLayout($i * 2 + 1, "Home {$i}"));
        $fixture->setRelation('awayTeam', teamForLayout($i * 2 + 2, "Away {$i}"));

        return $fixture;
    });
}

function emptyRound(): Collection
{
    return collect();
}

function buildLayout(Collection $ro32, Collection $ro16 = new Collection, Collection $qf = new Collection, Collection $sf = new Collection, Collection $final = new Collection): array
{
    return app(CircularBracketLayout::class)->build(
        $ro32, $ro16, $qf, $sf, $final,
        centerX: 400, centerY: 400, leafRadius: 340,
    );
}

test('positions all 32 teams around the circle', function () {
    $layout = buildLayout(ro32FixturesForLayout());

    expect($layout['leaves'])->toHaveCount(32);
});

test('always draws the full 31-connector tree, decided or not', function () {
    $layout = buildLayout(ro32FixturesForLayout());

    // 16 RO32 + 8 RO16 + 4 QF + 2 SF + 1 Final-to-trophy = 31.
    expect($layout['connectors'])->toHaveCount(31)
        ->and($layout['nodes'])->toBeEmpty(); // no winners yet.

    foreach ($layout['connectors'] as $connector) {
        expect($connector['arcA'])->toContain('A ')
            ->and($connector['arcB'])->toContain('A ')
            ->and($connector['winningSide'])->toBeNull();
    }

    // Every RO32 junction always has its own real fixture attached (used
    // for the hover tooltip), even before it's been played.
    expect($layout['connectors'][0]['fixture'])->toBeInstanceOf(Fixture::class);
});

test('the final connects straight to the trophy with no extra ring', function () {
    $layout = buildLayout(ro32FixturesForLayout());

    $finalConnector = end($layout['connectors']);
    expect($finalConnector['arcA'])->toContain('A 0.000000 0.000000')
        ->and($finalConnector['arcB'])->toContain('A 0.000000 0.000000');
});

test('a decided round-of-32 match centers its winner on the junction, flags the winning side and its color', function () {
    $ro32 = ro32FixturesForLayout();

    /** @var Fixture $decided */
    $decided = $ro32->first();
    $decided->status = FixtureStatus::Finished;
    $decided->home_score = 2;
    $decided->away_score = 0;

    $layout = buildLayout($ro32);

    expect($layout['nodes'])->toHaveCount(1);
    expect($layout['connectors'][0]['winningSide'])->toBe('a')
        // "Home 0" isn't in the known team-color map, so it falls back to gold.
        ->and($layout['connectors'][0]['winningColor'])->toBe('#fbbf24');

    $node = $layout['nodes'][0];
    $leafHome = $layout['leaves'][0];
    $leafAway = $layout['leaves'][1];

    expect($node['team']->id)->toBe($decided->homeTeam->id)
        // The node sits at the midpoint angle, not on either leaf's own angle.
        ->and($node['x'])->not->toBe($leafHome['x'])
        ->and($node['x'])->toBeGreaterThan(min($leafHome['x'], $leafAway['x']))
        ->and($node['x'])->toBeLessThan(max($leafHome['x'], $leafAway['x']));
});

test('a winner recognized in the team color map is highlighted in its own color', function () {
    $ro32 = ro32FixturesForLayout();

    /** @var Fixture $decided */
    $decided = $ro32->first();
    $decided->status = FixtureStatus::Finished;
    $decided->home_score = 2;
    $decided->away_score = 0;
    $decided->setRelation('homeTeam', teamForLayout(1, 'Canada'));

    $layout = buildLayout($ro32);

    expect($layout['connectors'][0]['winningColor'])->toBe('#FF0000');
});

test('the losing leaf is flagged eliminated, the winning leaf is not', function () {
    $ro32 = ro32FixturesForLayout();

    /** @var Fixture $decided */
    $decided = $ro32->first();
    $decided->status = FixtureStatus::Finished;
    $decided->home_score = 2;
    $decided->away_score = 0;

    $layout = buildLayout($ro32);

    expect($layout['leaves'][0]['eliminated'])->toBeFalse() // home, won
        ->and($layout['leaves'][1]['eliminated'])->toBeTrue(); // away, lost
});

test('a winner cascades into the next ring once its next-round match is also finished', function () {
    $ro32 = ro32FixturesForLayout();

    /** @var Fixture $match0 */
    $match0 = $ro32[0];
    $match0->status = FixtureStatus::Finished;
    $match0->home_score = 2;
    $match0->away_score = 0; // "Home 0" (team 1) wins.

    /** @var Fixture $match1 */
    $match1 = $ro32[1];
    $match1->status = FixtureStatus::Finished;
    $match1->home_score = 0;
    $match1->away_score = 1; // "Away 1" (team 4) wins.

    $ro16Fixture = Fixture::factory()->make([
        'round' => FixtureRound::RoundOf16, 'status' => FixtureStatus::Finished,
        'home_team_id' => 1, 'away_team_id' => 4, 'home_score' => 3, 'away_score' => 1,
    ]);
    $ro16Fixture->setRelation('homeTeam', teamForLayout(1, 'Home 0'));
    $ro16Fixture->setRelation('awayTeam', teamForLayout(4, 'Away 1'));

    $layout = buildLayout($ro32, collect([$ro16Fixture]));

    // 2 RO32 winners + 1 RO16 winner = 3 winner nodes placed.
    expect($layout['nodes'])->toHaveCount(3);

    $ro16Node = collect($layout['nodes'])->last();
    expect($ro16Node['team']->id)->toBe(1)
        ->and($ro16Node['eliminated'])->toBeFalse();

    // Team 4's ring-1 node (they won RO32) is now flagged eliminated,
    // since they lost the RO16 match — they stay on the bracket, dimmed,
    // rather than being erased.
    $team4RingOneNode = collect($layout['nodes'])->firstWhere('team.id', 4);
    expect($team4RingOneNode['eliminated'])->toBeTrue();
});
