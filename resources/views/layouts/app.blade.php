<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Copa 2026') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-slate-950 text-slate-100 antialiased">
        <nav class="border-b border-white/10 bg-slate-950/80 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <span class="font-semibold tracking-tight text-white">Copa do Mundo 2026</span>
                <div class="flex gap-4 text-sm font-medium text-slate-300">
                    <a href="{{ route('jogos') }}" wire:navigate
                        class="rounded-md px-3 py-1.5 transition hover:bg-white/10 hover:text-white {{ request()->routeIs('jogos') ? 'bg-white/10 text-white' : '' }}">
                        Jogos
                    </a>
                    <a href="{{ route('classificacao') }}" wire:navigate
                        class="rounded-md px-3 py-1.5 transition hover:bg-white/10 hover:text-white {{ request()->routeIs('classificacao') ? 'bg-white/10 text-white' : '' }}">
                        Classificação
                    </a>
                </div>
            </div>
        </nav>

        <main>
            {{ $slot }}
        </main>
    </body>
</html>
