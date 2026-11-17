<?php

use App\Events\GameFinished;
use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\GameShotCall;
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
use App\Services\Fantasy\ShotCallBoost;
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
        ->assertJsonPath('meta.round.lock_at', $this->monday->start_time->subMinutes(10)->toIso8601String());

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

it('o mercado fecha 10 minutos antes do primeiro jogo da rodada', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);
    $this->putJson('/api/fantasy/team', lineup())->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-11-09 18:49', 'America/New_York'));
    expect(app(FantasyMarket::class)->lockStarted())->toBe(0);

    Carbon::setTestNow(Carbon::parse('2026-11-09 18:50', 'America/New_York'));
    expect(app(FantasyMarket::class)->lockStarted())->toBe(1)
        ->and($this->r4->fresh()->market_status)->toBe(Round::LOCKED);

    // O time da rodada 4 não muda mais.
    $this->putJson('/api/fantasy/team', lineup(['cestinha_id' => 2]))->assertOk()->assertJsonPath('data.cestinha_id', 2);
    expect(FantasyTeam::where('round_id', $this->r4->id)->value('cestinha_id'))->toBe(1);
});

it('depois do horário de fechamento não salva, mesmo com o agendador atrasado', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);

    // 18:55: já passou do fechamento (18:50), mas o lockStarted ainda não rodou.
    Carbon::setTestNow(Carbon::parse('2026-11-09 18:55', 'America/New_York'));
    expect($this->r4->fresh()->market_status)->toBe(Round::OPEN);

    $this->putJson('/api/fantasy/team', lineup())->assertUnprocessable()
        ->assertJsonFragment(['O mercado está fechado: a rodada já vai começar.']);
});

it('ao fechar, abre o mercado da próxima rodada com os preços provisórios', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);
    $this->putJson('/api/fantasy/team', lineup())->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-11-09 18:50', 'America/New_York'));
    app(FantasyMarket::class)->lockStarted();

    expect($this->r5->fresh()->market_status)->toBe(Round::OPEN)
        ->and(PlayerPrice::where('round_id', $this->r5->id)->pluck('price', 'player_id')->map(fn ($p) => (float) $p)->all())
        ->toEqual(PlayerPrice::where('round_id', $this->r4->id)->pluck('price', 'player_id')->map(fn ($p) => (float) $p)->all());

    // A tela mostra a rodada 5 para escalar e a 4 (em andamento) com as parciais.
    $this->getJson('/api/fantasy/team')->assertOk()
        ->assertJsonPath('data.round.number', 5)
        ->assertJsonPath('data.round.status', 'OPEN')
        ->assertJsonPath('data.round.provisional_prices', true)
        ->assertJsonPath('data.team', null)
        ->assertJsonPath('data.patrimony', 100)
        ->assertJsonPath('data.previous.round.number', 4)
        ->assertJsonPath('data.previous.team.cost', 65);
});

it('o time pré-montado é reprecificado na apuração e avisa se passar do orçamento', function () {
    app(FantasyMarket::class)->open($this->r4);
    PlayerPrice::where('round_id', $this->r4->id)->update(['price' => 19]);
    $this->actingAs($this->ana);
    $this->putJson('/api/fantasy/team', lineup())->assertOk()->assertJsonPath('data.balance', 5); // 5 × 19 = 95

    Carbon::setTestNow(Carbon::parse('2026-11-09 23:00', 'America/New_York'));
    app(FantasyMarket::class)->lockStarted();

    // Rodada 5 montada com preços provisórios (19 cada) e o patrimônio do time da 4 (100).
    $this->putJson('/api/fantasy/team', ['players' => [3, 2, 6, 5, 8], 'cestinha_id' => 6])->assertOk()
        ->assertJsonPath('data.cost', 95)->assertJsonPath('data.patrimony', 100);

    // Rodada 4: os 5 da Ana zeram e desvalorizam 15% (19 → 16,15). 3, 6 e 8 não jogaram: seguem 19.
    $this->monday->update(['status' => 'FINAL', 'home_score' => 100, 'away_score' => 90]);
    foreach ([1, 2, 4, 5, 7] as $id) {
        PlayerGameStat::create(['game_id' => $this->monday->id, 'player_id' => $id, 'team_id' => Player::find($id)->team_id, 'pts' => 0]);
    }
    GameFinished::dispatch($this->monday);
    Carbon::setTestNow(Carbon::parse('2026-11-16 08:00', 'America/New_York'));
    app(RoundSettlement::class)->settleFinished();

    // Patrimônio = saldo 5 + 5 × 16,15 = 85,75. Custo = 3 × 19 + 2 × 16,15 = 89,30.
    $team = FantasyTeam::with('players')->where('round_id', $this->r5->id)->sole();
    expect((float) $team->patrimony)->toBe(85.75)
        ->and((float) $team->balance)->toBe(-3.55)
        ->and($team->players->pluck('bought_at_price', 'player_id')->map(fn ($p) => (float) $p)->sortKeys()->all())
        ->toBe([2 => 16.15, 3 => 19.0, 5 => 16.15, 6 => 19.0, 8 => 19.0]);

    $this->getJson('/api/fantasy/team')->assertJsonPath('data.round.provisional_prices', false)->assertJsonPath('data.previous', null);
    $kinds = collect($this->getJson('/api/notifications')->json('data'))->pluck('kind');
    expect($kinds)->toContain('lineup_over_budget')->toContain('round_settled');

    // Para salvar de novo, tem que caber no patrimônio novo.
    $this->putJson('/api/fantasy/team', ['players' => [3, 2, 6, 5, 8], 'cestinha_id' => 6])->assertUnprocessable();
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

    $team = $this->getJson('/api/fantasy/team')->assertOk()->json('data.previous.team'); // a rodada 5 já abriu
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

    // A dona do time fica sabendo da apuração pelo sininho.
    $this->getJson('/api/notifications')
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonPath('data.0.kind', 'round_settled')
        ->assertJsonPath('data.0.title', "Rodada {$this->r4->number} apurada")
        ->assertJsonPath('data.0.url', '/time');
    expect($this->getJson('/api/notifications')->json('data.0.body'))->toStartWith('Seu time fez 174,00 pontos.');
});

it('os acertos de palpite nos jogos da rodada viram boost nos pontos do time', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);
    $this->putJson('/api/fantasy/team', lineup())->assertOk();

    // Palpite no jogo de segunda (rodada 4): BOS vence por 6–10 com o Jogador 1 de MVP.
    $this->putJson("/api/games/{$this->monday->id}/shot-call", ['winner_team_id' => $this->bos->id, 'margin_band' => 'M16_PLUS', 'mvp_player_id' => 1])->assertOk();
    // Palpite num jogo de outra rodada não conta.
    $other = fantasyGame('401000902', '2026-11-16 19:00', $this->r5);
    $this->putJson("/api/games/{$other->id}/shot-call", ['winner_team_id' => $this->bos->id, 'margin_band' => 'M1_5', 'mvp_player_id' => 1])->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-11-09 23:00', 'America/New_York'));
    app(FantasyMarket::class)->lockStarted();
    $this->monday->update(['status' => 'FINAL', 'home_score' => 108, 'away_score' => 100]);
    foreach ([1 => 50, 2 => 4, 4 => 30, 5 => 16, 7 => 24] as $id => $pts) {
        PlayerGameStat::create(['game_id' => $this->monday->id, 'player_id' => $id, 'team_id' => Player::find($id)->team_id, 'pts' => $pts]);
    }
    GameFinished::dispatch($this->monday);

    // Acertou vencedor (+1%) e MVP (+3%), errou a margem: boost de 4% já aparece na parcial.
    $team = $this->getJson('/api/fantasy/team')->assertOk()->json('data.previous.team'); // a rodada 5 já abriu
    expect($team['base_points'])->toEqual(174)
        ->and($team['boost'])->toMatchArray(['value' => 0.04, 'calls' => 1, 'cap' => 0.3, 'hits' => ['winner' => 1, 'margin' => 0, 'mvp' => 1]])
        ->and($team['points'])->toEqual(180.96);

    Carbon::setTestNow(Carbon::parse('2026-11-16 08:00', 'America/New_York'));
    app(RoundSettlement::class)->settleFinished();

    expect(FantasyTeam::where('round_id', $this->r4->id)->first())
        ->base_points->toEqual(174)->boost->toEqual(0.04)->points->toEqual(180.96);
});

it('o boost tem teto por rodada', function () {
    app(FantasyMarket::class)->open($this->r4);
    $this->actingAs($this->ana);
    $this->putJson('/api/fantasy/team', lineup())->assertOk();

    // 12 jogos acertados em cheio (+6% cada = 72%) ficam em +30%.
    for ($i = 0; $i < 12; $i++) {
        $g = fantasyGame('40100100'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), '2026-11-10 20:00', $this->r4, 'FINAL');
        GameShotCall::create(['user_id' => $this->ana->id, 'game_id' => $g->id, 'winner_team_id' => $this->bos->id,
            'margin_band' => 'M6_10', 'mvp_player_id' => 1, 'status' => 'SETTLED', 'correct' => 3, 'points' => 6,
            'markets' => ['winner' => true, 'margin' => true, 'mvp' => true], 'settled_at' => now()]);
    }

    $boost = app(ShotCallBoost::class)->forUser($this->ana->id, $this->r4);
    expect($boost)->toMatchArray(['boost' => 0.3, 'raw' => 0.72, 'calls' => 12]);
});

it('a página do jogador mostra preço, histórico, jogos e próximos jogos', function () {
    app(FantasyMarket::class)->open($this->r4);
    PlayerGameStat::create(['game_id' => '401000900', 'player_id' => 1, 'team_id' => $this->bos->id, 'minutes' => '34:10', 'pts' => 30, 'reb' => 8, 'ast' => 7]);
    $this->actingAs($this->ana);

    $res = $this->getJson('/api/players/1')->assertOk()
        ->assertJsonPath('data.name', 'Jogador 1')
        ->assertJsonPath('data.role', 'PG')
        ->assertJsonPath('data.team.tricode', 'BOS')
        ->assertJsonPath('data.price', 20)
        ->assertJsonPath('data.min_to_gain', 40)
        ->assertJsonPath('data.games_in_round', 1)
        ->assertJsonPath('data.season.games', 1)
        ->assertJsonPath('data.season.average', 40)
        ->assertJsonPath('data.price_history.0.round', 4)
        ->assertJsonCount(1, 'data.games')
        ->assertJsonPath('data.games.0.home', true)
        ->assertJsonPath('data.games.0.opponent.tricode', 'LAL')
        ->assertJsonPath('data.games.0.points', 40)
        ->assertJsonPath('data.games.0.line.pts', 30)
        ->assertJsonPath('data.upcoming.0.id', $this->monday->id);

    // Calouro sem jogo: sem média.
    $this->getJson('/api/players/3')->assertOk()->assertJsonPath('data.season.average', null)->assertJsonCount(0, 'data.games');
    $this->getJson('/api/players/999')->assertNotFound();
});
