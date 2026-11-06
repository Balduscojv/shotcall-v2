<?php

use App\Events\GameFinished;
use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerGameScore;
use App\Models\PlayerGameStat;
use App\Models\PlayerPrice;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\Fantasy\FantasyMarket;
use App\Services\Fantasy\RoundSettlement;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

// Sexta, 6/nov: a rodada 3 (2 a 8/nov) está rolando; a 4 (9 a 15/nov) é a que abre.
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-06 12:00', 'America/New_York'));
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');

    $this->season = Season::first();
    $this->r3 = Round::where('number', 3)->first();
    $this->r4 = Round::where('number', 4)->first();
    $this->r5 = Round::where('number', 5)->first();
    $this->bos = Team::where('tricode', 'BOS')->first();
    $this->lal = Team::where('tricode', 'LAL')->first();

    // Posições: 1 PG, 2 SG, 3 PG, 4 SF, 5 PF, 6 SF, 7 C, 8 C. Média fantasy na temporada até agora:
    $averages = [1 => 40, 2 => 20, 3 => null, 4 => 30, 5 => 16, 6 => 60, 7 => 24, 8 => 2];
    foreach ($averages as $id => $avg) {
        Player::create([
            'id' => $id, 'full_name' => "Jogador {$id}", 'active' => true,
            'team_id' => $id % 2 ? $this->bos->id : $this->lal->id,
            'position' => $id <= 3 ? 'G' : ($id <= 6 ? 'F' : 'C'),
            'role' => [1 => 'PG', 2 => 'SG', 3 => 'PG', 4 => 'SF', 5 => 'PF', 6 => 'SF', 7 => 'C', 8 => 'C'][$id],
        ]);
    }
    $past = fantasyGame('401000900', '2026-11-04 20:00', $this->r3, 'FINAL');
    foreach (array_filter($averages) as $id => $avg) {
        PlayerGameScore::create(['game_id' => $past->id, 'player_id' => $id, 'points' => $avg, 'breakdown' => []]);
    }

    // Segunda, 9/nov, 19h: primeiro jogo da rodada 4.
    $this->monday = fantasyGame('401000901', '2026-11-09 19:00', $this->r4);
    $this->ana = User::factory()->create(['handle' => 'ana']);
});

afterEach(fn () => Carbon::setTestNow());

function fantasyGame(string $id, string $et, Round $round, string $status = 'SCHEDULED'): Game
{
    return Game::create([
        'id' => $id, 'season_id' => $round->season_id, 'round_id' => $round->id, 'phase' => 'REGULAR',
        'home_team_id' => test()->bos->id, 'away_team_id' => test()->lal->id,
        'start_time' => Carbon::parse($et, 'America/New_York')->utc(), 'status' => $status,
    ]);
}

/** PG 1, SG 2, SF 4, PF 5, C 7: 20 + 10 + 15 + 8 + 12 = 65. */
function lineup(array $overrides = []): array
{
    return $overrides + ['players' => [1, 2, 4, 5, 7], 'cestinha_id' => 1];
}

it('abre o mercado com o preço pela média da temporada', function () {
    test()->artisan('fantasy:open', ['round' => 4])->expectsOutputToContain('Rodada 4 aberta: 8 preço(s)')->assertSuccessful();

    expect($this->r4->fresh()->market_status)->toBe(Round::OPEN)
        ->and($this->r3->fresh()->market_status)->toBe(Round::LOCKED)
        ->and(PlayerPrice::where('round_id', $this->r4->id)->pluck('price', 'player_id')->map(fn ($p) => (float) $p)->all())
        // metade da média, entre 2 e 25; sem jogo, preço de novato (5)
        ->toEqual([1 => 20.0, 2 => 10.0, 3 => 5.0, 4 => 15.0, 5 => 8.0, 6 => 25.0, 7 => 12.0, 8 => 2.0]);
});

it('o mercado lista preço, média e jogos na rodada', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);

    $res = $this->getJson('/api/fantasy/market')->assertOk()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('meta.round.number', 4)
        ->assertJsonPath('meta.round.status', 'OPEN')
        ->assertJsonPath('meta.round.lock_at', $this->monday->start_time->toIso8601String());

    expect(collect($res->json('data'))->firstWhere('id', 1))
        ->toMatchArray(['position' => 'G', 'price' => 20, 'average' => 40, 'games_played' => 1, 'games_in_round' => 1, 'min_to_gain' => 40]);
});

it('escala o time dentro do patrimônio', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);

    $this->getJson('/api/fantasy/team')->assertOk()->assertJsonPath('data.team', null)->assertJsonPath('data.starting_balance', 100);

    $this->putJson('/api/fantasy/team', lineup())->assertOk()
        ->assertJsonPath('data.cost', 65)
        ->assertJsonPath('data.balance', 35)
        ->assertJsonPath('data.patrimony', 100);

    $team = $this->getJson('/api/fantasy/team')->assertOk()->json('data.team');
    expect(array_column($team['players'], 'slot'))->toBe(['PG', 'SG', 'SF', 'PF', 'C'])
        ->and(array_column($team['players'], 'id'))->toBe([1, 2, 4, 5, 7])
        ->and($team['players'][0]['cestinha'])->toBeTrue();

    // Trocar alguém: continua 1 time por rodada.
    $this->putJson('/api/fantasy/team', lineup(['players' => [3, 2, 4, 5, 7], 'cestinha_id' => 2]))->assertOk()->assertJsonPath('data.balance', 50);
    expect(FantasyTeam::count())->toBe(1);
});

it('valida a escalação', function (array $data, string $message) {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);

    $this->putJson('/api/fantasy/team', $data)->assertUnprocessable()->assertJsonFragment([$message]);
})->with([
    '2 armadores (PG)' => [fn () => lineup(['players' => [1, 2, 3, 4, 7]]), 'Escale 1 armador (PG).'],
    'sem ala-pivô' => [fn () => lineup(['players' => [1, 2, 3, 4, 7]]), 'Escale 1 ala-pivô (PF).'],
    'sem pivô' => [fn () => lineup(['players' => [1, 2, 4, 5, 6]]), 'Escale 1 pivô (C).'],
    'cestinha de fora' => [fn () => lineup(['cestinha_id' => 6]), 'Escolha o cestinha entre os 5 escalados.'],
    'repetido' => [fn () => lineup(['players' => [1, 1, 4, 5, 7]]), 'Tem jogador repetido na escalação.'],
    'faltando' => [fn () => lineup(['players' => [1, 2, 4, 5]]), 'Escale exatamente 5 jogadores.'],
    'fora do mercado' => [fn () => lineup(['players' => [1, 2, 4, 5, 99]]), 'Algum jogador escolhido não está no mercado desta rodada.'],
]);

it('não deixa estourar o patrimônio', function () {
    app(FantasyMarket::class)->open($this->r4);
    PlayerPrice::where('round_id', $this->r4->id)->update(['price' => 25]);
    $this->actingAs($this->ana);

    $this->putJson('/api/fantasy/team', lineup())->assertUnprocessable()
        ->assertJsonFragment(['Patrimônio insuficiente: o time custa F$ 125,00 e você tem F$ 100,00.']);
});

it('o mercado fecha no primeiro jogo da rodada', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);
    $this->putJson('/api/fantasy/team', lineup())->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-11-09 19:00', 'America/New_York'));
    expect(app(FantasyMarket::class)->lockStarted())->toBe(1)
        ->and($this->r4->fresh()->market_status)->toBe(Round::LOCKED);

    $this->putJson('/api/fantasy/team', lineup(['cestinha_id' => 2]))->assertUnprocessable()
        ->assertJsonFragment(['O mercado está fechado: a rodada já começou.']);
});

it('mostra a parcial com jogo ao vivo, cestinha em dobro', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);
    $this->putJson('/api/fantasy/team', lineup())->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-11-09 20:00', 'America/New_York'));
    app(FantasyMarket::class)->lockStarted();
    $this->monday->update(['status' => 'LIVE']);
    foreach ([1 => 10, 2 => 4, 7 => 6] as $id => $pts) {
        PlayerGameStat::create(['game_id' => $this->monday->id, 'player_id' => $id, 'team_id' => Player::find($id)->team_id, 'pts' => $pts]);
    }

    $team = $this->getJson('/api/fantasy/team')->assertOk()->json('data.team');
    // 10 × 2 (cestinha) + 4 + 6
    expect($team['points'])->toEqual(30)
        ->and($team['players'][0])->toMatchArray(['points' => 20, 'live' => true, 'cestinha' => true]);
});

it('apura a rodada: pontos, valorização e o time segue para a próxima', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);
    $this->putJson('/api/fantasy/team', lineup())->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-11-09 23:00', 'America/New_York'));
    app(FantasyMarket::class)->lockStarted();
    $this->monday->update(['status' => 'FINAL', 'home_score' => 100, 'away_score' => 90]);
    foreach ([1 => 50, 2 => 4, 4 => 30, 5 => 16, 7 => 24] as $id => $pts) {
        PlayerGameStat::create(['game_id' => $this->monday->id, 'player_id' => $id, 'team_id' => Player::find($id)->team_id, 'pts' => $pts]);
    }
    GameFinished::dispatch($this->monday); // pontua o jogo e soma na rodada

    // A rodada 3 já acabou e é apurada; a 4 só depois do domingo.
    app(RoundSettlement::class)->settleFinished();
    expect($this->r3->fresh()->market_status)->toBe(Round::SETTLED)
        ->and($this->r4->fresh()->market_status)->toBe(Round::LOCKED);

    Carbon::setTestNow(Carbon::parse('2026-11-16 08:00', 'America/New_York'));
    expect(app(RoundSettlement::class)->settleFinished())->toBe(1);

    // 50 × 2 + 4 + 30 + 16 + 24 = 174
    expect(FantasyTeam::where('round_id', $this->r4->id)->value('points'))->toEqual(174)
        ->and($this->r4->fresh()->market_status)->toBe(Round::SETTLED)
        ->and($this->r5->fresh()->market_status)->toBe(Round::OPEN);

    // Valorização: Jogador 1 custava 20 e fez 50 (mínimo para valorizar: 40) → +1,00.
    // Jogador 2 custava 10 e fez 4 (mínimo 20) → −1,50 (limite de 15%).
    $p5 = PlayerPrice::where('round_id', $this->r5->id)->get()->keyBy('player_id');
    expect((float) $p5[1]->price)->toBe(21.0)->and((float) $p5[1]->variation)->toBe(1.0)
        ->and((float) $p5[2]->price)->toBe(8.5);

    // O time segue para a rodada 5 com os mesmos jogadores; patrimônio = saldo + valor novo.
    $next = FantasyTeam::with('players')->where('round_id', $this->r5->id)->first();
    $value = $next->players->sum(fn ($p) => (float) $p5[$p->player_id]->price);
    expect($next->players->pluck('player_id')->sort()->values()->all())->toBe([1, 2, 4, 5, 7])
        ->and((int) $next->cestinha_id)->toBe(1)
        ->and((float) $next->balance)->toBe(35.0)
        ->and((float) $next->patrimony)->toBe(round(35 + $value, 2));

    $this->getJson('/api/fantasy/team')->assertJsonPath('data.round.number', 5)->assertJsonPath('data.team.patrimony', round(35 + $value, 2));
});
