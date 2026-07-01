<?php

use App\Enums\FixtureRound;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Services\Bracket\CircularBracketLayout;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function ro32Fixtures()
    {
        return $this->fixturesForRound(FixtureRound::RoundOf32);
    }

    #[Computed]
    public function bracket(): ?array
    {
        if ($this->ro32Fixtures->count() !== 16) {
            return null;
        }

        return app(CircularBracketLayout::class)->build($this->ro32Fixtures, $this->fixturesForRound(FixtureRound::RoundOf16), $this->fixturesForRound(FixtureRound::QuarterFinal), $this->fixturesForRound(FixtureRound::SemiFinal), $this->fixturesForRound(FixtureRound::Final), centerX: 400, centerY: 400, leafRadius: 340);
    }

    #[Computed]
    public function hasLiveFixtures(): bool
    {
        return Fixture::where('status', FixtureStatus::Live)->exists();
    }

    /**
     * One hover-tooltip payload per connector, in the same order as
     * bracket()['connectors'] — embedded client-side (as a plain JSON
     * string in a data-* attribute, not a JS expression) so hovering
     * doesn't need a Livewire round-trip, and so it's read fresh at
     * hover-time from an attribute Livewire keeps current across polls.
     */
    public function connectorTooltips(): string
    {
        $payloads = collect($this->bracket['connectors'] ?? [])
            ->map(fn(array $connector) => $this->connectorData($connector['fixture']))
            ->all();

        return json_encode($payloads);
    }

    /**
     * @return array<string, mixed>
     */
    private function connectorData(?Fixture $fixture): array
    {
        if (!$fixture) {
            return [
                'known' => false,
                'home' => null,
                'away' => null,
                'homeFlag' => null,
                'awayFlag' => null,
                'when' => null,
                'status' => null,
                'homeScore' => null,
                'awayScore' => null,
            ];
        }

        $hasScore = in_array($fixture->status, [FixtureStatus::Finished, FixtureStatus::Live], true);

        // Penalty shootout scores flank the regular-time score in
        // parentheses, e.g. "(4) 2" and "2 (5)" either side of the "x".
        $homeScore = $hasScore ? ($fixture->home_pens !== null ? "({$fixture->home_pens}) {$fixture->home_score}" : (string) $fixture->home_score) : null;
        $awayScore = $hasScore ? ($fixture->away_pens !== null ? "{$fixture->away_score} ({$fixture->away_pens})" : (string) $fixture->away_score) : null;

        return [
            'known' => true,
            'home' => $fixture->homeTeam?->name ?? ($fixture->home_placeholder ?? 'A definir'),
            'away' => $fixture->awayTeam?->name ?? ($fixture->away_placeholder ?? 'A definir'),
            'homeFlag' => $fixture->homeTeam?->flag_url,
            'awayFlag' => $fixture->awayTeam?->flag_url,
            'when' => $fixture->kickoff_at->translatedFormat('d M, H:i'),
            'status' => $fixture->status->value,
            'homeScore' => $homeScore,
            'awayScore' => $awayScore,
            'lastPolledAt' => $fixture->status === FixtureStatus::Live ? $fixture->last_polled_at?->toIso8601String() : null,
        ];
    }

    private function fixturesForRound(FixtureRound $round)
    {
        // Order by football-data.org's own match number, not our import
        // (insertion) order — the external numbering follows the official
        // bracket sheet, so sequential-adjacent leaves really do face each
        // other in the next round. Sorting by our auto-increment id would
        // reflect import/kickoff-date order instead, scrambling the tree.
        return Fixture::query()
            ->where('round', $round)
            ->with(['homeTeam', 'awayTeam'])
            ->orderByRaw('CAST(external_id_football_data AS INTEGER)')
            ->get();
    }
};
?>

<div @if ($this->hasLiveFixtures) wire:poll.15s @endif>
    @if (!$this->bracket)
        <p class="text-center text-slate-400">
            Chaveamento ainda não disponível — aguardando o fim da fase de grupos e a rodada de 32 ser definida.
        </p>
    @else
        {{-- `wire:key` keeps this exact node across wire:poll morphs so Alpine's
        x-data survives; the tooltip payloads live in `data-labels` (a plain
        attribute Livewire *does* refresh on every poll) and are only read at
        hover-time into `current`, so a tooltip opened after several polls
        always shows the latest score/timestamp instead of whatever was on
        the page at first paint. --}}
        <div wire:key="bracket-canvas" class="relative" x-data="{ hovered: null, tooltip: { x: 0, y: 0 }, freshness: null, current: null }"
            data-labels="{{ $this->connectorTooltips() }}">
            <svg viewBox="0 0 800 800" class="mx-auto w-full max-w-225">
                <defs>
                    <radialGradient id="glow" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#facc15" stop-opacity="0.35" />
                        <stop offset="100%" stop-color="#facc15" stop-opacity="0" />
                    </radialGradient>
                    <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#fde68a" />
                        <stop offset="55%" stop-color="#f59e0b" />
                        <stop offset="100%" stop-color="#92400e" />
                    </linearGradient>
                    <clipPath id="leafClip">
                        <circle cx="20" cy="20" r="20" />
                    </clipPath>
                    <clipPath id="nodeClip">
                        <circle cx="17" cy="17" r="17" />
                    </clipPath>
                </defs>

                <circle cx="400" cy="400" r="220" fill="url(#glow)" />

                @foreach ($this->bracket['connectors'] as $connector)
                    @php
                        $highlight = fn(bool $isWinningHalf) => $isWinningHalf
                            ? ['stroke' => $connector['winningColor'], 'opacity' => 1, 'width' => 3.4]
                            : ['stroke' => '#fbbf24', 'opacity' => 0.45, 'width' => 2.2];
                        $sideA = $highlight($connector['winningSide'] === 'a');
                        $sideB = $highlight($connector['winningSide'] === 'b');
                        $isLive = $connector['fixture']?->status === FixtureStatus::Live;
                    @endphp
                    <g wire:key="connector-{{ $loop->index }}"
                        @mouseenter="hovered = {{ $loop->index }};
                        current = JSON.parse($root.dataset.labels)[{{ $loop->index }}];
                        const r = $el.getBoundingClientRect(), c = $root.getBoundingClientRect(); tooltip = { x: r.left - c.left + r.width / 2, y: r.top - c.top };
                        freshness = current.lastPolledAt ? Math.max(0, Math.floor((Date.now() - new Date(current.lastPolledAt).getTime()) / 1000)) : null"
                        @mouseleave="hovered = null"
                        :style="hovered === {{ $loop->index }} ? 'filter: drop-shadow(0 0 6px rgba(255,255,255,0.9))' : ''">
                        <path d="{{ $connector['arcA'] }}" fill="none" stroke-linecap="round"
                            stroke="{{ $sideA['stroke'] }}" stroke-opacity="{{ $sideA['opacity'] }}"
                            stroke-width="{{ $sideA['width'] }}" />
                        <path d="{{ $connector['arcB'] }}" fill="none" stroke-linecap="round"
                            stroke="{{ $sideB['stroke'] }}" stroke-opacity="{{ $sideB['opacity'] }}"
                            stroke-width="{{ $sideB['width'] }}" />
                        <path d="{{ $connector['lineA'] }}" fill="none" stroke-linecap="round"
                            stroke="{{ $sideA['stroke'] }}" stroke-opacity="{{ $sideA['opacity'] }}"
                            stroke-width="{{ $sideA['width'] }}" />
                        <path d="{{ $connector['lineB'] }}" fill="none" stroke-linecap="round"
                            stroke="{{ $sideB['stroke'] }}" stroke-opacity="{{ $sideB['opacity'] }}"
                            stroke-width="{{ $sideB['width'] }}" />
                        @if ($isLive)
                            {{-- A slow, discreet pulse behind a live match's whole wedge. --}}
                            <path d="{{ $connector['zone'] }}" fill="rgba(16,185,129,0.10)" class="animate-pulse" />
                        @endif
                        {{-- The whole wedge is the hover target — a soft, barely-there fill
                    on hover instead of only the thin line reacting. --}}
                        <path d="{{ $connector['zone'] }}" pointer-events="fill"
                            :fill="hovered === {{ $loop->index }} ? 'rgba(255,255,255,0.05)' : 'transparent'" />
                        {{-- TEMP DEBUG: every zone outlined at once, remove after review. --}}
                        {{-- <path d="{{ $connector['zone'] }}" fill="rgba(34,211,238,0.08)" stroke="cyan" stroke-width="1" /> --}}
                    </g>
                @endforeach

                {{-- Trophy, hand-drawn in SVG (no external image / trademarked photo). --}}
                <g transform="translate(400, 400)">
                    <path d="M -20 -32 C -20 -10, -15 6, 0 9 C 15 6, 20 -10, 20 -32 Z" fill="url(#gold)"
                        stroke="#78350f" stroke-width="1" />
                    <path d="M -20 -27 C -34 -27, -34 -6, -18 -4" fill="none" stroke="url(#gold)" stroke-width="4"
                        stroke-linecap="round" />
                    <path d="M 20 -27 C 34 -27, 34 -6, 18 -4" fill="none" stroke="url(#gold)" stroke-width="4"
                        stroke-linecap="round" />
                    <rect x="-3" y="9" width="6" height="15" fill="url(#gold)" />
                    <path d="M -16 24 L 16 24 L 11 33 L -11 33 Z" fill="url(#gold)" stroke="#78350f" stroke-width="1" />
                </g>

                @foreach ($this->bracket['leaves'] as $leaf)
                    <g wire:key="leaf-{{ $loop->index }}"
                        transform="translate({{ $leaf['x'] - 20 }}, {{ $leaf['y'] - 20 }})">
                        <circle cx="20" cy="20" r="20" fill="#1e293b" />
                        @if ($leaf['team']?->flag_url)
                            <image href="{{ $leaf['team']->flag_url }}" x="0" y="0" width="40" height="40"
                                preserveAspectRatio="xMidYMid slice" clip-path="url(#leafClip)"
                                opacity="{{ $leaf['eliminated'] ? 0.35 : 1 }}" />
                        @endif
                        <circle cx="20" cy="20" r="20" fill="none" stroke="#475569" stroke-width="1" />
                    </g>
                @endforeach

                @foreach ($this->bracket['nodes'] as $node)
                    <g wire:key="node-{{ $loop->index }}"
                        transform="translate({{ $node['x'] - 17 }}, {{ $node['y'] - 17 }})">
                        <circle cx="17" cy="17" r="17" fill="#1e293b" />
                        @if ($node['team']?->flag_url)
                            <image href="{{ $node['team']->flag_url }}" x="0" y="0" width="34" height="34"
                                preserveAspectRatio="xMidYMid slice" clip-path="url(#nodeClip)"
                                opacity="{{ $node['eliminated'] ? 0.35 : 1 }}" />
                        @endif
                        <circle cx="17" cy="17" r="17" fill="none" stroke="#475569"
                            stroke-width="1" />
                    </g>
                @endforeach
            </svg>

            <div x-show="hovered !== null" x-cloak
                class="pointer-events-none absolute z-20 w-72 -translate-x-1/2 -translate-y-full rounded-xl border border-white/10 bg-slate-950/95 p-3 text-white shadow-2xl backdrop-blur-sm"
                :style="`left: ${tooltip.x}px; top: ${tooltip.y - 14}px;`">
                <template x-if="hovered !== null">
                    <div>
                        <template x-if="current.known">
                            <div>
                                <div class="flex items-center justify-between gap-1.5">
                                    <div class="flex min-w-0 flex-1 items-center gap-1.5">
                                        <img :src="current.homeFlag" x-show="current.homeFlag"
                                            class="h-5 w-5 shrink-0 rounded-full border border-slate-600 object-cover" />
                                        <span class="truncate text-xs font-medium" x-text="current.home"></span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1 text-sm font-bold"
                                        :class="current.status === 'live' ? 'text-emerald-400' : 'text-white'">
                                        <span x-show="current.homeScore" x-text="current.homeScore"></span>
                                        <span class="text-[10px] font-normal text-slate-500">×</span>
                                        <span x-show="current.awayScore" x-text="current.awayScore"></span>
                                    </div>
                                    <div class="flex min-w-0 flex-1 items-center justify-end gap-1.5">
                                        <span class="truncate text-right text-xs font-medium"
                                            x-text="current.away"></span>
                                        <img :src="current.awayFlag" x-show="current.awayFlag"
                                            class="h-5 w-5 shrink-0 rounded-full border border-slate-600 object-cover" />
                                    </div>
                                </div>
                                <div
                                    class="mt-2.5 flex items-center justify-center gap-1.5 border-t border-white/10 pt-2.5 text-center text-[11px] text-slate-400">
                                    <template x-if="current.status === 'live'">
                                        <span class="relative flex h-1.5 w-1.5">
                                            <span
                                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                            <span
                                                class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        </span>
                                    </template>
                                    <span
                                        x-text="current.status === 'live'
                                    ? (freshness === null ? 'Aguardando atualização' : freshness < 60 ? `Atualizado há ${freshness}s` : `Atualizado há ${Math.floor(freshness / 60)}min`)
                                    : current.when"></span>
                                </div>
                            </div>
                        </template>
                        <template x-if="!current.known">
                            <p class="text-center text-xs text-slate-400">Confronto ainda não definido</p>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">
            Design inspirado em <a href="https://www.instagram.com/emiliosansolini/" target="_blank" rel="noopener"
                class="text-slate-300 underline hover:text-white">@emiliosansolini</a>
        </p>
    @endif
</div>
