<?php

namespace App\Services\Standings;

use App\Models\Team;

class StandingRow
{
    /**
     * @param  array<int, 'W'|'D'|'L'>  $lastFive  Most recent result first.
     */
    public function __construct(
        public readonly Team $team,
        public readonly int $played,
        public readonly int $won,
        public readonly int $drawn,
        public readonly int $lost,
        public readonly int $goalsFor,
        public readonly int $goalsAgainst,
        public readonly array $lastFive,
    ) {}

    public function points(): int
    {
        return $this->won * 3 + $this->drawn;
    }

    public function goalDifference(): int
    {
        return $this->goalsFor - $this->goalsAgainst;
    }
}
