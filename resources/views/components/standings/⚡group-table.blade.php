<?php

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Group;
use App\Services\Standings\StandingsCalculator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function groups()
    {
        return Group::with('teams')->orderBy('name')->get();
    }

    #[Computed]
    public function standingsByGroup()
    {
        $calculator = app(StandingsCalculator::class);

        return $this->groups->mapWithKeys(fn (Group $group) => [$group->id => $calculator->forGroup($group)]);
    }

    #[Computed]
    public function hasLiveFixtures(): bool
    {
        return Fixture::where('status', FixtureStatus::Live)->exists();
    }
};
?>

<div class="grid gap-6 md:grid-cols-2" @if ($this->hasLiveFixtures) wire:poll.15s @endif>
    @foreach ($this->groups as $group)
        <div wire:key="group-{{ $group->id }}" class="overflow-hidden rounded-xl border border-white/10 bg-slate-900/60">
            <h2 class="border-b border-white/10 px-4 py-3 text-lg font-bold text-white">Grupo {{ $group->name }}</h2>
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-slate-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Equipe</th>
                        <th class="px-2 py-2">Pts</th>
                        <th class="px-2 py-2">PJ</th>
                        <th class="px-2 py-2">VIT</th>
                        <th class="px-2 py-2">E</th>
                        <th class="px-2 py-2">DER</th>
                        <th class="px-2 py-2">GM</th>
                        <th class="px-2 py-2">GC</th>
                        <th class="px-2 py-2">SG</th>
                        <th class="px-4 py-2 text-left">Resultados</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->standingsByGroup[$group->id] as $position => $row)
                        <tr wire:key="row-{{ $row->team->id }}" class="border-t border-white/5">
                            <td class="px-4 py-2 font-medium text-white">
                                <span class="mr-2 text-slate-500">{{ $position + 1 }}</span>
                                {{ $row->team->name }}
                            </td>
                            <td class="px-2 py-2 text-center font-semibold">{{ $row->points() }}</td>
                            <td class="px-2 py-2 text-center text-slate-300">{{ $row->played }}</td>
                            <td class="px-2 py-2 text-center text-slate-300">{{ $row->won }}</td>
                            <td class="px-2 py-2 text-center text-slate-300">{{ $row->drawn }}</td>
                            <td class="px-2 py-2 text-center text-slate-300">{{ $row->lost }}</td>
                            <td class="px-2 py-2 text-center text-slate-300">{{ $row->goalsFor }}</td>
                            <td class="px-2 py-2 text-center text-slate-300">{{ $row->goalsAgainst }}</td>
                            <td class="px-2 py-2 text-center text-slate-300">{{ $row->goalDifference() }}</td>
                            <td class="px-4 py-2">
                                <div class="flex gap-1">
                                    @foreach ($row->results as $result)
                                        <span wire:key="form-{{ $row->team->id }}-{{ $loop->index }}"
                                            class="h-4 w-4 rounded-full {{ match ($result) {
                                                'W' => 'bg-emerald-500',
                                                'D' => 'bg-slate-500',
                                                'L' => 'bg-red-500',
                                            } }}"></span>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
