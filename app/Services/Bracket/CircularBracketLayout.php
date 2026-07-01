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
     *
     * @param  Collection<int, Fixture>  $ro32Fixtures  Exactly 16 fixtures, in bracket order.
     * @param  Collection<int, Fixture>  $ro16Fixtures  Up to 8 fixtures.
     * @param  Collection<int, Fixture>  $qfFixtures  Up to 4 fixtures.
     * @param  Collection<int, Fixture>  $sfFixtures  Up to 2 fixtures.
     * @return array{leaves: array<int, array<string, mixed>>, nodes: array<int, array<string, mixed>>, connectors: array<int, array<string, mixed>>}
     */
    public function build(
        Collection $ro32Fixtures,
        Collection $ro16Fixtures,
        Collection $qfFixtures,
        Collection $sfFixtures,
        float $centerX,
        float $centerY,
        float $leafRadius,
    ): array {
        $slotCount = $ro32Fixtures->count() * 2;

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
                    'angle' => rad2deg($angleRad),
                    'team' => $team,
                    'placeholder' => $placeholder,
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

            $connectors[] = ['path' => $this->pairPath($centerX, $centerY, $leafRadius, $ringRadii[0], $angleA, $angleB)];

            $winner = $fixture->winner();

            if ($winner) {
                $nodes[] = [...$this->cartesian($centerX, $centerY, $ringRadii[0], $midAngle), 'team' => $winner];
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

                $connectors[] = ['path' => $this->pairPath($centerX, $centerY, $outerR, $innerR, $a['angle'], $b['angle'])];

                $winner = $this->realWinner($roundFixtures, $a['team'], $b['team']);

                if ($winner) {
                    $nodes[] = [...$this->cartesian($centerX, $centerY, $innerR, $midAngle), 'team' => $winner];
                }

                $next[intdiv($keys[$i], 2)] = ['angle' => $midAngle, 'team' => $winner];
            }

            $current = $next;
        }

        // The Final: the last 2 junctions cross straight into the trophy.
        $remaining = array_values($current);

        if (count($remaining) === 2) {
            $connectors[] = ['path' => $this->pairPath($centerX, $centerY, $ringRadii[3], 0, $remaining[0]['angle'], $remaining[1]['angle'])];
        }

        return ['leaves' => $leaves, 'nodes' => $nodes, 'connectors' => $connectors];
    }

    /**
     * The real fixture (in this round) between two known teams, if any,
     * and its winner — only meaningful once both feeders are decided.
     *
     * @param  Collection<int, Fixture>  $roundFixtures
     */
    private function realWinner(Collection $roundFixtures, ?Team $teamA, ?Team $teamB): ?Team
    {
        if (! $teamA || ! $teamB) {
            return null;
        }

        $fixture = $roundFixtures->first(
            fn (Fixture $f) => in_array($f->home_team_id, [$teamA->id, $teamB->id], true)
                && in_array($f->away_team_id, [$teamA->id, $teamB->id], true),
        );

        return $fixture?->winner();
    }

    /**
     * A radial line from each side down to a shared inner radius, an arc
     * joining them there (the circular equivalent of a bracket's
     * horizontal bar), then on to the other side. When $innerRadius is 0
     * both "inner" points collapse onto the center, so the arc disappears
     * and the two lines simply meet at the trophy.
     */
    private function pairPath(float $centerX, float $centerY, float $outerRadius, float $innerRadius, float $angleA, float $angleB): string
    {
        $outerA = $this->cartesian($centerX, $centerY, $outerRadius, $angleA);
        $outerB = $this->cartesian($centerX, $centerY, $outerRadius, $angleB);
        $innerA = $this->cartesian($centerX, $centerY, $innerRadius, $angleA);
        $innerB = $this->cartesian($centerX, $centerY, $innerRadius, $angleB);

        return sprintf(
            'M %F %F L %F %F A %F %F 0 0 1 %F %F L %F %F',
            $outerA['x'], $outerA['y'],
            $innerA['x'], $innerA['y'],
            $innerRadius, $innerRadius,
            $innerB['x'], $innerB['y'],
            $outerB['x'], $outerB['y'],
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
