<?php

namespace App\Services\Bracket;

use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Support\Collection;

class CircularBracketLayout
{
    /**
     * One ring per bracket junction between the 32 leaves and the trophy:
     * the Round-of-32 match itself, then RO16, QF, SF. The Final has no
     * ring of its own — its connector goes straight to the trophy.
     */
    protected const RINGS = 4;

    /**
     * Radial breathing room, in pixels, left between every pair of
     * adjacent rings' hover wedges — split evenly (half on each side) so
     * neither wedge encroaches on its neighbor's own territory.
     */
    protected const RING_GAP = 3.0;

    /**
     * Builds the full circular bracket tree — every junction is always
     * drawn, decided or not, since the tree's shape is fixed the moment
     * the 32 leaves are seeded (adjacent leaves always face each other,
     * their winner always meets the adjacent pair's winner, and so on).
     * That's only true because leaves are ordered by football-data.org's
     * own match number, which follows the official bracket sheet — sort
     * by anything else (e.g. our import id) and this stops holding.
     *
     * Each junction's winner (if that real fixture is finished) is placed
     * centered on the junction — the midpoint angle between its two
     * feeders — and that midpoint becomes the team's position for the
     * *next* junction too, so a team's flag marches straight toward the
     * trophy each round it survives, rather than sitting off to one side.
     * A connector's winning side is flagged so it can be drawn more
     * prominently than the rest of the (still-undecided) tree. A team
     * that's out is flagged `eliminated` wherever it still appears (its
     * leaf, and any ring it reached) rather than removed outright.
     *
     * @param  Collection<int, Fixture>  $ro32Fixtures  Exactly 16 fixtures, in bracket order.
     * @param  Collection<int, Fixture>  $ro16Fixtures  Up to 8 fixtures.
     * @param  Collection<int, Fixture>  $qfFixtures  Up to 4 fixtures.
     * @param  Collection<int, Fixture>  $sfFixtures  Up to 2 fixtures.
     * @param  Collection<int, Fixture>  $finalFixtures  Up to 1 fixture.
     * @return array{leaves: array<int, array<string, mixed>>, nodes: array<int, array<string, mixed>>, connectors: array<int, array<string, mixed>>}
     */
    public function build(
        Collection $ro32Fixtures,
        Collection $ro16Fixtures,
        Collection $qfFixtures,
        Collection $sfFixtures,
        Collection $finalFixtures,
        float $centerX,
        float $centerY,
        float $leafRadius,
    ): array {
        $slotCount = $ro32Fixtures->count() * 2;
        $eliminated = $this->eliminatedTeamIds($ro32Fixtures, $ro16Fixtures, $qfFixtures, $sfFixtures);

        $leaves = [];
        $nodes = [];
        $connectors = [];

        /** @var array<int, float> $leafAngle */
        $leafAngle = [];

        foreach ($ro32Fixtures->values() as $matchIndex => $fixture) {
            foreach ([[$matchIndex * 2, $fixture->homeTeam, $fixture->home_placeholder], [$matchIndex * 2 + 1, $fixture->awayTeam, $fixture->away_placeholder]] as [$slot, $team, $placeholder]) {
                $angleRad = $this->angle($slot, $slotCount);
                $leafAngle[$slot] = $angleRad;

                $leaves[] = [
                    ...$this->cartesian($centerX, $centerY, $leafRadius, $angleRad),
                    'team' => $team,
                    'placeholder' => $placeholder,
                    'eliminated' => $team && isset($eliminated[$team->id]),
                ];
            }
        }

        $ringRadii = $this->ringRadii($leafRadius);

        // Ring 1: each RO32 fixture's own bracket junction.
        /** @var array<int, array{angle: float, team: ?Team}> $current */
        $current = [];

        foreach ($ro32Fixtures->values() as $matchIndex => $fixture) {
            $angleA = $leafAngle[$matchIndex * 2];
            $angleB = $leafAngle[$matchIndex * 2 + 1];
            $midAngle = ($angleA + $angleB) / 2;
            $winner = $fixture->winner();

            $connectors[] = $this->connector($centerX, $centerY, $leafRadius, $ringRadii[0], $angleA, $angleB, $this->winningSide($winner, $fixture->homeTeam, $fixture->awayTeam), $winner, $fixture);

            if ($winner) {
                $nodes[] = [...$this->cartesian($centerX, $centerY, $ringRadii[0], $midAngle), 'team' => $winner, 'eliminated' => isset($eliminated[$winner->id])];
            }

            $current[$matchIndex] = ['angle' => $midAngle, 'team' => $winner];
        }

        // Rings 2-4: RO16, QF, SF — pair the previous ring's adjacent
        // junctions, resolving each one's real winner (if both feeders
        // are decided and that real fixture has been played).
        foreach ([$ro16Fixtures, $qfFixtures, $sfFixtures] as $level => $roundFixtures) {
            $outerR = $ringRadii[$level];
            $innerR = $ringRadii[$level + 1];
            $keys = array_keys($current);
            sort($keys);

            $next = [];

            for ($i = 0; $i < count($keys); $i += 2) {
                $a = $current[$keys[$i]];
                $b = $current[$keys[$i + 1]];
                $midAngle = ($a['angle'] + $b['angle']) / 2;

                $matchedFixture = $this->realFixture($roundFixtures, $a['team'], $b['team']);
                $winner = $matchedFixture?->winner();

                $connectors[] = $this->connector($centerX, $centerY, $outerR, $innerR, $a['angle'], $b['angle'], $this->winningSide($winner, $a['team'], $b['team']), $winner, $matchedFixture);

                if ($winner) {
                    $nodes[] = [...$this->cartesian($centerX, $centerY, $innerR, $midAngle), 'team' => $winner, 'eliminated' => isset($eliminated[$winner->id])];
                }

                $next[intdiv($keys[$i], 2)] = ['angle' => $midAngle, 'team' => $winner];
            }

            $current = $next;
        }

        // The Final: the last 2 junctions cross straight into the trophy.
        $remaining = array_values($current);

        if (count($remaining) === 2) {
            $finalFixture = $this->realFixture($finalFixtures, $remaining[0]['team'], $remaining[1]['team']);
            $finalWinner = $finalFixture?->winner();

            $connectors[] = $this->connector($centerX, $centerY, $ringRadii[3], 0, $remaining[0]['angle'], $remaining[1]['angle'], $this->winningSide($finalWinner, $remaining[0]['team'], $remaining[1]['team']), $finalWinner, $finalFixture);
        }

        return ['leaves' => $leaves, 'nodes' => $nodes, 'connectors' => $connectors];
    }

    /**
     * Every team that has lost a decided match, across every round —
     * used to flag their leaf/nodes as eliminated (dimmed) rather than
     * removing them, so the bracket keeps showing where they were
     * knocked out instead of just erasing their run.
     *
     * @param  Collection<int, Fixture>  ...$rounds
     * @return array<int, true>
     */
    private function eliminatedTeamIds(Collection ...$rounds): array
    {
        $eliminated = [];

        foreach ($rounds as $round) {
            foreach ($round as $fixture) {
                $winner = $fixture->winner();

                if (! $winner) {
                    continue;
                }

                $loserId = $fixture->home_team_id === $winner->id ? $fixture->away_team_id : $fixture->home_team_id;

                if ($loserId) {
                    $eliminated[$loserId] = true;
                }
            }
        }

        return $eliminated;
    }

    /**
     * Which side of a junction (if either) the real, decided winner sits
     * on — used to draw that specific radial segment more prominently.
     */
    private function winningSide(?Team $winner, ?Team $teamA, ?Team $teamB): ?string
    {
        return match (true) {
            $winner === null => null,
            $teamA && $teamA->id === $winner->id => 'a',
            $teamB && $teamB->id === $winner->id => 'b',
            default => null,
        };
    }

    /**
     * The real fixture (in this round) between two known teams, if any —
     * only findable once both feeders are decided. Used both for its
     * winner (to keep cascading the bracket) and, regardless of whether
     * it's finished yet, to show its kickoff time/score on hover.
     *
     * @param  Collection<int, Fixture>  $roundFixtures
     */
    private function realFixture(Collection $roundFixtures, ?Team $teamA, ?Team $teamB): ?Fixture
    {
        if (! $teamA || ! $teamB) {
            return null;
        }

        return $roundFixtures->first(
            fn (Fixture $f) => in_array($f->home_team_id, [$teamA->id, $teamB->id], true)
                && in_array($f->away_team_id, [$teamA->id, $teamB->id], true),
        );
    }

    /**
     * A radial line from each side down to a shared inner radius, an arc
     * joining them there (the circular equivalent of a bracket's
     * horizontal bar), then on to the other side. The arc is split in
     * two at its midpoint — exactly where the winner's flag node sits —
     * so when there's a decided winner, their line *and* their half of
     * the arc can be highlighted together as one unbroken path in their
     * own team color, all the way to the flag. When $innerRadius is 0
     * both "inner" points collapse onto the center, so the arcs
     * disappear and the two lines simply meet at the trophy.
     *
     * @return array{lineA: string, lineB: string, arcA: string, arcB: string, zone: string, winningSide: ?string, winningColor: string, fixture: ?Fixture}
     */
    private function connector(float $centerX, float $centerY, float $outerRadius, float $innerRadius, float $angleA, float $angleB, ?string $winningSide, ?Team $winner, ?Fixture $fixture): array
    {
        $outerA = $this->cartesian($centerX, $centerY, $outerRadius, $angleA);
        $outerB = $this->cartesian($centerX, $centerY, $outerRadius, $angleB);
        $innerA = $this->cartesian($centerX, $centerY, $innerRadius, $angleA);
        $innerB = $this->cartesian($centerX, $centerY, $innerRadius, $angleB);
        $innerMid = $this->cartesian($centerX, $centerY, $innerRadius, ($angleA + $angleB) / 2);

        return [
            'lineA' => sprintf('M %F %F L %F %F', $outerA['x'], $outerA['y'], $innerA['x'], $innerA['y']),
            'lineB' => sprintf('M %F %F L %F %F', $outerB['x'], $outerB['y'], $innerB['x'], $innerB['y']),
            'arcA' => sprintf('M %F %F A %F %F 0 0 1 %F %F', $innerA['x'], $innerA['y'], $innerRadius, $innerRadius, $innerMid['x'], $innerMid['y']),
            'arcB' => sprintf('M %F %F A %F %F 0 0 1 %F %F', $innerMid['x'], $innerMid['y'], $innerRadius, $innerRadius, $innerB['x'], $innerB['y']),
            'zone' => $this->zonePath($centerX, $centerY, $outerRadius, $innerRadius, $angleA, $angleB),
            'winningSide' => $winningSide,
            'winningColor' => TeamColors::for($winner),
            'fixture' => $fixture,
        ];
    }

    /**
     * The wedge (annular sector) this match occupies — used as the hover
     * target and highlight fill, instead of only the thin connector line
     * itself. Angularly it reaches out toward the neighboring wedge on
     * either side, almost touching it (see angular pad note below).
     *
     * Radially, a ring's outer edge is the *exact same radius* as the
     * ring outside it's inner edge — they share a boundary with zero
     * natural gap. Every wedge (including the outermost, against the
     * leaves) is inset by half of self::RING_GAP on both its outer and
     * inner edge, which leaves exactly RING_GAP of breathing room between
     * every pair of adjacent rings *and* keeps every ring's own radial
     * height identical — no ring reads as visually "taller" than the
     * rest. The one edge with no neighbor to protect against is the
     * innermost connector's inner edge (the Final, $innerRadius === 0),
     * which stays at dead center rather than leaving a pointless gap
     * before the trophy.
     *
     * The angular pad targets a constant pixel gap (self::ANGULAR_GAP) at
     * the wedge's outer radius, converted to radians for that radius
     * (arc-length = radius × angle, so angle = arc-length / radius) —
     * *not* a fixed fraction of the wedge's own span. A fixed fraction
     * seems tempting (every ring's span-to-neighbor-gap ratio is equal
     * thanks to the uniform binary halving that builds every ring), but
     * inner rings have far wider spans than outer ones, so a fixed
     * *fraction* balloons into a much wider gap in pixels the further in
     * you go. Converting from a target pixel width keeps every ring's
     * gap looking the same regardless of how wide that ring's wedges
     * are. It's capped at 40% of the wedge's own span so a tiny wedge
     * can never invert itself. The Final's connector has no neighbor to
     * reach toward (span is exactly half the circle), so it gets no
     * angular padding at all.
     */
    private function zonePath(float $centerX, float $centerY, float $outerRadius, float $innerRadius, float $angleA, float $angleB): string
    {
        $span = $angleB - $angleA;
        $angularPad = $span < M_PI ? $span * 0.5 : 0.0;
        $paddedA = $angleA - $angularPad;
        $paddedB = $angleB + $angularPad;
        $largeArc = ($paddedB - $paddedA) > M_PI ? 1 : 0;

        $halfGap = self::RING_GAP / 3;
        $outerPad = $outerRadius - $halfGap;
        $innerPad = $innerRadius === 0.0 ? 0.0 : $innerRadius + $halfGap;

        $outerA = $this->cartesian($centerX, $centerY, $outerPad, $paddedA);
        $outerB = $this->cartesian($centerX, $centerY, $outerPad, $paddedB);
        $innerA = $this->cartesian($centerX, $centerY, $innerPad, $paddedA);
        $innerB = $this->cartesian($centerX, $centerY, $innerPad, $paddedB);

        return sprintf(
            'M %F %F A %F %F 0 %d 1 %F %F L %F %F A %F %F 0 %d 0 %F %F Z',
            $outerA['x'], $outerA['y'], $outerPad, $outerPad, $largeArc, $outerB['x'], $outerB['y'],
            $innerB['x'], $innerB['y'], $innerPad, $innerPad, $largeArc, $innerA['x'], $innerA['y'],
        );
    }

    /**
     * @return array{x: float, y: float}
     */
    private function cartesian(float $centerX, float $centerY, float $radius, float $angleRad): array
    {
        return [
            'x' => $centerX + $radius * cos($angleRad),
            'y' => $centerY + $radius * sin($angleRad),
        ];
    }

    /**
     * Rotated so the Final's connector — the last two junctions left
     * standing after halving the circle three times — lands exactly on
     * the horizontal axis (0°/180°) instead of wherever the leaf count
     * happens to put it. With adjacent-pair halving, those two junctions
     * are centered on leaves [0, slotCount/2) and [slotCount/2, slotCount)
     * respectively, i.e. at the mean slot index of each half.
     */
    private function angle(int $slot, int $slotCount): float
    {
        $degreesPerSlot = 360 / $slotCount;
        $firstHalfMeanSlot = ($slotCount / 4) - 0.5;
        $offset = -$degreesPerSlot * $firstHalfMeanSlot;

        return deg2rad(($slot * $degreesPerSlot) + $offset);
    }

    /**
     * 4 ring radii, evenly spaced all the way from the leaves down to the
     * trophy — 5 equal-width segments in total (leaf→ring1→ring2→ring3→
     * ring4→center), so the Final's drop to the trophy is the same width
     * as every other ring gap instead of a leftover, disproportionate gap.
     *
     * @return array<int, float>
     */
    private function ringRadii(float $leafRadius): array
    {
        $step = $leafRadius / (self::RINGS + 1);

        return array_map(fn ($i) => $leafRadius - $step * ($i + 1), range(0, self::RINGS - 1));
    }
}
