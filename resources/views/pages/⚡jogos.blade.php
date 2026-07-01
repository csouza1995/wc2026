<?php

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function fixturesByDate()
    {
        return Fixture::query()
            ->with(['homeTeam', 'awayTeam', 'group'])
            ->orderBy('kickoff_at')
            ->get()
            ->groupBy(fn (Fixture $fixture) => $fixture->kickoff_at->toDateString());
    }

    #[Computed]
    public function hasLiveFixtures(): bool
    {
        return Fixture::where('status', FixtureStatus::Live)->exists();
    }
};
?>

<div class="mx-auto max-w-4xl px-4 py-8" @if ($this->hasLiveFixtures) wire:poll.15s @endif>
    @forelse ($this->fixturesByDate as $date => $fixtures)
        <div wire:key="date-{{ $date }}" class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">
                {{ Carbon::parse($date)->translatedFormat('d \d\e F, Y') }}
            </h2>
            <div class="space-y-2">
                @foreach ($fixtures as $fixture)
                    <div wire:key="fixture-{{ $fixture->id }}"
                        class="flex items-center justify-between rounded-lg border border-white/10 bg-slate-900/60 px-4 py-3">
                        <div class="flex flex-1 items-center justify-end gap-2 text-right">
                            <span class="text-sm text-slate-200">{{ $fixture->homeTeam?->name ?? $fixture->home_placeholder ?? 'A definir' }}</span>
                        </div>

                        <div class="mx-4 flex w-24 flex-col items-center">
                            @if ($fixture->status === FixtureStatus::Finished)
                                <span class="text-base font-semibold text-white">{{ $fixture->home_score }} - {{ $fixture->away_score }}</span>
                            @elseif ($fixture->status === FixtureStatus::Live)
                                <span class="text-base font-semibold text-emerald-400">{{ $fixture->home_score ?? 0 }} - {{ $fixture->away_score ?? 0 }}</span>
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">
                                    Ao vivo{{ $fixture->minute ? " · {$fixture->minute}'" : '' }}
                                </span>
                            @else
                                <span class="text-sm text-slate-400">{{ $fixture->kickoff_at->format('H:i') }}</span>
                            @endif
                            <span class="text-[10px] text-slate-500">{{ $fixture->group?->name ? "Grupo {$fixture->group->name}" : $fixture->round->label() }}</span>
                        </div>

                        <div class="flex flex-1 items-center gap-2">
                            <span class="text-sm text-slate-200">{{ $fixture->awayTeam?->name ?? $fixture->away_placeholder ?? 'A definir' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-center text-slate-400">Nenhuma partida importada ainda.</p>
    @endforelse
</div>
