<?php

namespace App\Services\Football;

use App\Enums\DataSource;
use App\Models\ApiUsageLog;
use App\Models\Fixture;
use Illuminate\Support\Carbon;

class LiveScoreBudget
{
    /**
     * Below this many minutes since the last API-Football ping, force a
     * refresh regardless of the critical-window check — a safety net in
     * case football-data.org's heartbeat stalls without minute data.
     */
    protected const FALLBACK_INTERVAL_MINUTES = 10;

    public function __construct(private readonly int $dailyQuota) {}

    /**
     * Whether this fixture should be pinged against API-Football on this
     * tick, given today's remaining quota split across still-live matches.
     */
    public function shouldUseApiFootball(Fixture $fixture, int $liveMatchesToday): bool
    {
        $remaining = $this->remainingQuota();

        if ($remaining <= 0) {
            return false;
        }

        $perMatchBudget = intdiv($remaining, max($liveMatchesToday, 1));

        if ($perMatchBudget < 1) {
            return false;
        }

        if ($fixture->period?->isCriticalWindow($fixture->minute)) {
            return true;
        }

        return $this->pastFallbackInterval($fixture);
    }

    public function recordUsage(DataSource $source): void
    {
        // A plain where('date', ...) wouldn't reliably match the 'date'-cast
        // column (it round-trips through a datetime string on write), so we
        // compare with whereDate() on both the lookup and the count query.
        $log = ApiUsageLog::query()
            ->whereDate('date', Carbon::today())
            ->where('source', $source)
            ->first() ?? ApiUsageLog::create([
                'date' => Carbon::today(),
                'source' => $source,
                'requests_count' => 0,
            ]);

        $log->increment('requests_count');
    }

    public function remainingQuota(): int
    {
        $used = ApiUsageLog::query()
            ->whereDate('date', Carbon::today())
            ->where('source', DataSource::ApiFootball)
            ->value('requests_count') ?? 0;

        return max($this->dailyQuota - $used, 0);
    }

    private function pastFallbackInterval(Fixture $fixture): bool
    {
        if ($fixture->last_polled_source !== DataSource::ApiFootball || $fixture->last_polled_at === null) {
            return true;
        }

        return $fixture->last_polled_at->lt(now()->subMinutes(self::FALLBACK_INTERVAL_MINUTES));
    }
}
