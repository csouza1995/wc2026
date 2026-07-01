<?php

use Livewire\Component;

new class extends Component
{
    public string $tab = 'grupos';

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex gap-2 border-b border-white/10">
        <button type="button" wire:click="selectTab('grupos')"
            class="border-b-2 px-4 py-3 text-sm font-semibold transition {{ $tab === 'grupos' ? 'border-emerald-400 text-white' : 'border-transparent text-slate-400 hover:text-slate-200' }}">
            Fase de grupos
        </button>
        <button type="button" wire:click="selectTab('eliminatorias')"
            class="border-b-2 px-4 py-3 text-sm font-semibold transition {{ $tab === 'eliminatorias' ? 'border-emerald-400 text-white' : 'border-transparent text-slate-400 hover:text-slate-200' }}">
            Eliminatórias
        </button>
    </div>

    {{-- Each tab is its own component, only booted (and only querying the
    database) the first time its tab is opened — switching away destroys it,
    switching back mounts it fresh. --}}
    @if ($tab === 'grupos')
        <livewire:standings.group-table wire:key="group-table" />
    @else
        <livewire:standings.knockout-bracket wire:key="knockout-bracket" />
    @endif
</div>
