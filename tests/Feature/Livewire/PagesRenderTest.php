<?php

use App\Enums\FixtureRound;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\Team;
use Livewire\Livewire;

test('jogos page renders', function () {
    $this->get('/jogos')->assertOk()->assertSee('Nenhuma partida importada ainda.');
});

test('classificacao page defaults to the fase de grupos tab', function () {
    $group = Group::factory()->create(['name' => 'A']);
    $team = Team::factory()->create(['name' => 'Brazil']);
    $group->teams()->attach($team);

    $this->get('/classificacao')
        ->assertOk()
        ->assertSee('Fase de grupos')
        ->assertSee('Eliminatórias')
        ->assertSee('Grupo A')
        ->assertSee('Brazil');
});

test('switching to eliminatorias swaps in the knockout bracket component', function () {
    Livewire::test('pages::classificacao')
        ->assertSeeLivewire('standings.group-table')
        ->call('selectTab', 'eliminatorias')
        ->assertSeeLivewire('standings.knockout-bracket');
});

test('knockout bracket shows a waiting message before the round of 32 is fully known', function () {
    Livewire::test('standings.knockout-bracket')
        ->assertSee('Chaveamento ainda não disponível');
});

test('knockout bracket renders the circular svg once all 16 round-of-32 fixtures exist', function () {
    Fixture::factory()->count(16)->create(['round' => FixtureRound::RoundOf32]);

    Livewire::test('standings.knockout-bracket')
        ->assertDontSee('Chaveamento ainda não disponível')
        ->assertSee('<svg', false);
});
