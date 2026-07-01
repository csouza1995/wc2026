<?php

use App\Enums\FixtureRound;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Services\Bracket\CircularBracketLayout;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
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

        return app(CircularBracketLayout::class)->build(
            $this->ro32Fixtures,
            $this->fixturesForRound(FixtureRound::RoundOf16),
            $this->fixturesForRound(FixtureRound::QuarterFinal),
            $this->fixturesForRound(FixtureRound::SemiFinal),
            centerX: 400,
            centerY: 400,
            leafRadius: 340,
        );
    }

    #[Computed]
    public function hasLiveFixtures(): bool
    {
        return Fixture::where('status', FixtureStatus::Live)->exists();
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
    @if (! $this->bracket)
        <p class="text-center text-slate-400">
            Chaveamento ainda não disponível — aguardando o fim da fase de grupos e a rodada de 32 ser definida.
        </p>
    @else
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
                <clipPath id="leafClip"><circle cx="20" cy="20" r="20" /></clipPath>
                <clipPath id="nodeClip"><circle cx="17" cy="17" r="17" /></clipPath>
            </defs>

            <circle cx="400" cy="400" r="220" fill="url(#glow)" />

            @foreach ($this->bracket['connectors'] as $connector)
                @php
                    $highlight = fn (bool $isWinningHalf) => $isWinningHalf
                        ? ['stroke' => $connector['winningColor'], 'opacity' => 1, 'width' => 3.4]
                        : ['stroke' => '#fbbf24', 'opacity' => 0.45, 'width' => 2.2];
                    $sideA = $highlight($connector['winningSide'] === 'a');
                    $sideB = $highlight($connector['winningSide'] === 'b');
                @endphp
                <path wire:key="arc-a-{{ $loop->index }}" d="{{ $connector['arcA'] }}" fill="none" stroke-linecap="round"
                    stroke="{{ $sideA['stroke'] }}" stroke-opacity="{{ $sideA['opacity'] }}" stroke-width="{{ $sideA['width'] }}" />
                <path wire:key="arc-b-{{ $loop->index }}" d="{{ $connector['arcB'] }}" fill="none" stroke-linecap="round"
                    stroke="{{ $sideB['stroke'] }}" stroke-opacity="{{ $sideB['opacity'] }}" stroke-width="{{ $sideB['width'] }}" />
                <path wire:key="line-a-{{ $loop->index }}" d="{{ $connector['lineA'] }}" fill="none" stroke-linecap="round"
                    stroke="{{ $sideA['stroke'] }}" stroke-opacity="{{ $sideA['opacity'] }}" stroke-width="{{ $sideA['width'] }}" />
                <path wire:key="line-b-{{ $loop->index }}" d="{{ $connector['lineB'] }}" fill="none" stroke-linecap="round"
                    stroke="{{ $sideB['stroke'] }}" stroke-opacity="{{ $sideB['opacity'] }}" stroke-width="{{ $sideB['width'] }}" />
            @endforeach

            {{-- Trophy, hand-drawn in SVG (no external image / trademarked photo). --}}
            <g transform="translate(400, 400)">
                <path d="M -20 -32 C -20 -10, -15 6, 0 9 C 15 6, 20 -10, 20 -32 Z" fill="url(#gold)" stroke="#78350f" stroke-width="1" />
                <path d="M -20 -27 C -34 -27, -34 -6, -18 -4" fill="none" stroke="url(#gold)" stroke-width="4" stroke-linecap="round" />
                <path d="M 20 -27 C 34 -27, 34 -6, 18 -4" fill="none" stroke="url(#gold)" stroke-width="4" stroke-linecap="round" />
                <rect x="-3" y="9" width="6" height="15" fill="url(#gold)" />
                <path d="M -16 24 L 16 24 L 11 33 L -11 33 Z" fill="url(#gold)" stroke="#78350f" stroke-width="1" />
            </g>

            @foreach ($this->bracket['leaves'] as $leaf)
                <g wire:key="leaf-{{ $loop->index }}" transform="translate({{ $leaf['x'] - 20 }}, {{ $leaf['y'] - 20 }})">
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
                <g wire:key="node-{{ $loop->index }}" transform="translate({{ $node['x'] - 17 }}, {{ $node['y'] - 17 }})">
                    <circle cx="17" cy="17" r="17" fill="#1e293b" />
                    @if ($node['team']?->flag_url)
                        <image href="{{ $node['team']->flag_url }}" x="0" y="0" width="34" height="34"
                            preserveAspectRatio="xMidYMid slice" clip-path="url(#nodeClip)"
                            opacity="{{ $node['eliminated'] ? 0.35 : 1 }}" />
                    @endif
                    <circle cx="17" cy="17" r="17" fill="none" stroke="#475569" stroke-width="1" />
                </g>
            @endforeach
        </svg>

        <p class="mt-8 text-center text-xs text-slate-500">
            Design inspirado em <a href="https://www.instagram.com/emiliosansolini/" target="_blank" rel="noopener"
                class="text-slate-300 underline hover:text-white">@emiliosansolini</a>
        </p>
    @endif
</div>
