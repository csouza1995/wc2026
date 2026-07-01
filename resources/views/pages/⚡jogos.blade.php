<?php

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public bool $showFilterModal = false;

    public string $teamSearch = '';

    #[Url]
    public bool $onlyLive = false;

    #[Url]
    public bool $onlyToday = false;

    /** @var array<int, int> */
    #[Url]
    public array $selectedTeamIds = [];

    public function toggleLive(): void
    {
        $this->onlyLive = ! $this->onlyLive;
    }

    public function toggleToday(): void
    {
        $this->onlyToday = ! $this->onlyToday;
    }

    public function toggleTeam(int $teamId): void
    {
        if (in_array($teamId, $this->selectedTeamIds, true)) {
            $this->selectedTeamIds = array_values(array_diff($this->selectedTeamIds, [$teamId]));

            return;
        }

        if (count($this->selectedTeamIds) >= 3) {
            return;
        }

        $this->selectedTeamIds[] = $teamId;
    }

    public function clearFilter(): void
    {
        $this->selectedTeamIds = [];
    }

    #[Computed]
    public function allTeams()
    {
        return Team::orderBy('name')->get();
    }

    #[Computed]
    public function filteredTeamOptions()
    {
        if ($this->teamSearch === '') {
            return $this->allTeams;
        }

        $needle = Str::lower($this->teamSearch);

        return $this->allTeams->filter(fn (Team $team) => str_contains(Str::lower($team->name), $needle))->values();
    }

    #[Computed]
    public function selectedTeams()
    {
        return $this->allTeams->whereIn('id', $this->selectedTeamIds)->values();
    }

    #[Computed]
    public function fixturesByDate()
    {
        return Fixture::query()
            ->with(['homeTeam', 'awayTeam', 'group'])
            ->when(
                count($this->selectedTeamIds) > 0,
                fn ($query) => $query->where(
                    fn ($query) => $query->whereIn('home_team_id', $this->selectedTeamIds)
                        ->orWhereIn('away_team_id', $this->selectedTeamIds),
                ),
            )
            ->when($this->onlyLive, fn ($query) => $query->where('status', FixtureStatus::Live))
            ->when($this->onlyToday, fn ($query) => $query->whereDate('kickoff_at', today()))
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
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="toggleLive"
                class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition {{ $onlyLive ? 'bg-emerald-500/20 text-emerald-400 ring-1 ring-emerald-400' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                </span>
                Ao vivo
            </button>
            <button type="button" wire:click="toggleToday"
                class="rounded-full px-3 py-1 text-xs font-semibold transition {{ $onlyToday ? 'bg-emerald-500/20 text-emerald-400 ring-1 ring-emerald-400' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                Hoje
            </button>

            @foreach ($this->selectedTeams as $team)
                <span wire:key="chip-{{ $team->id }}" class="flex items-center gap-1.5 rounded-full bg-slate-800 py-1 pl-1 pr-3 text-xs text-slate-200">
                    <x-team-flag :team="$team" size="5" />
                    {{ $team->name }}
                </span>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            @if (count($this->selectedTeamIds) > 0)
                <button type="button" wire:click="clearFilter" class="text-xs text-slate-400 underline hover:text-white">
                    Limpar filtro
                </button>
            @endif
            <button type="button" wire:click="$set('showFilterModal', true)"
                class="rounded-md border border-white/10 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-slate-700">
                Filtrar seleções
            </button>
        </div>
    </div>

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
                            <x-team-flag :team="$fixture->homeTeam" size="8" />
                        </div>

                        <div class="mx-4 flex w-24 flex-col items-center">
                            @if ($fixture->status === FixtureStatus::Finished)
                                <span class="text-base font-semibold text-white">{{ $fixture->home_score }} - {{ $fixture->away_score }}</span>
                            @elseif ($fixture->status === FixtureStatus::Live)
                                <span class="text-base font-semibold text-emerald-400">{{ $fixture->home_score ?? 0 }} - {{ $fixture->away_score ?? 0 }}</span>
                                <span class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-400">
                                    <x-live-indicator :last-polled-at="$fixture->last_polled_at" />
                                    Ao vivo{{ $fixture->minute ? " · {$fixture->minute}'" : '' }}
                                </span>
                            @else
                                <span class="text-sm text-slate-400">{{ $fixture->kickoff_at->format('H:i') }}</span>
                            @endif
                            <span class="text-[10px] text-slate-500">{{ $fixture->group?->name ? "Grupo {$fixture->group->name}" : $fixture->round->label() }}</span>
                        </div>

                        <div class="flex flex-1 items-center gap-2">
                            <x-team-flag :team="$fixture->awayTeam" size="8" />
                            <span class="text-sm text-slate-200">{{ $fixture->awayTeam?->name ?? $fixture->away_placeholder ?? 'A definir' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-center text-slate-400">
            {{ count($this->selectedTeamIds) > 0 || $onlyLive || $onlyToday ? 'Nenhum jogo encontrado para esses filtros.' : 'Nenhuma partida importada ainda.' }}
        </p>
    @endforelse

    @if ($showFilterModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4" wire:click.self="$set('showFilterModal', false)">
            <div class="max-h-[80vh] w-full max-w-lg overflow-hidden rounded-xl border border-white/10 bg-slate-900">
                <div class="border-b border-white/10 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold text-white">Filtrar por seleção ({{ count($selectedTeamIds) }}/3)</h2>
                        <button type="button" wire:click="$set('showFilterModal', false)" class="text-slate-400 hover:text-white">✕</button>
                    </div>
                    <input type="search" wire:model.live.debounce.300ms="teamSearch" placeholder="Buscar seleção..."
                        class="w-full rounded-md border border-white/10 bg-slate-800 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-400" />
                </div>

                <div class="grid max-h-96 grid-cols-4 gap-3 overflow-y-auto p-4 sm:grid-cols-5">
                    @forelse ($this->filteredTeamOptions as $team)
                        @php $isSelected = in_array($team->id, $selectedTeamIds, true); @endphp
                        <button type="button" wire:key="option-{{ $team->id }}" wire:click="toggleTeam({{ $team->id }})"
                            class="flex flex-col items-center gap-1 rounded-lg p-2 text-center transition {{ $isSelected ? 'bg-emerald-500/20 ring-2 ring-emerald-400' : 'hover:bg-white/5' }}">
                            <x-team-flag :team="$team" size="10" />
                            <span class="line-clamp-1 text-[11px] text-slate-300">{{ $team->name }}</span>
                        </button>
                    @empty
                        <p class="col-span-full text-center text-sm text-slate-500">Nenhuma seleção encontrada.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
