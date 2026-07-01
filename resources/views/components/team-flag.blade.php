@props(['team', 'size' => '8'])

@php
    $sizeClasses = match ((string) $size) {
        '5' => 'h-5 w-5',
        '8' => 'h-8 w-8',
        '10' => 'h-10 w-10',
        default => 'h-8 w-8',
    };
@endphp

@if ($team?->flag_url)
    <img src="{{ $team->flag_url }}" alt="{{ $team->name }}"
        {{ $attributes->merge(['class' => "{$sizeClasses} shrink-0 rounded-full border border-slate-600 object-cover"]) }} />
@else
    <span {{ $attributes->merge(['class' => "{$sizeClasses} shrink-0 rounded-full border border-slate-600 bg-slate-800"]) }}></span>
@endif
