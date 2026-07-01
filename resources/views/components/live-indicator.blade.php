@props(['lastPolledAt'])

<span x-data="liveFreshness('{{ $lastPolledAt?->toIso8601String() }}')" :title="label"
    {{ $attributes->merge(['class' => 'relative inline-flex h-2 w-2']) }}>
    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
</span>
