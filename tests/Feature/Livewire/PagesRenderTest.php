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

test('jogos filter narrows the list down to fixtures involving the selected teams', function () {
    [$brazil, $argentina, $france] = Team::factory()->count(3)->create();

    $brazilVsFrance = Fixture::factory()->create(['home_team_id' => $brazil->id, 'away_team_id' => $france->id]);
    Fixture::factory()->create(['home_team_id' => $argentina->id, 'away_team_id' => $france->id]);

    Livewire::test('pages::jogos')
        ->assertSee($brazil->name)
        ->call('toggleTeam', $brazil->id)
        ->assertSet('selectedTeamIds', [$brazil->id])
        ->assertSee($brazilVsFrance->homeTeam->name);
});

test('jogos filter caps selection at 3 teams', function () {
    $teams = Team::factory()->count(4)->create();

    $component = Livewire::test('pages::jogos');

    foreach ($teams as $team) {
        $component->call('toggleTeam', $team->id);
    }

    $component->assertSet('selectedTeamIds', $teams->take(3)->pluck('id')->all());
});
