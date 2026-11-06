<?php

use App\Integrations\Nba\EspnClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// Respostas reais da ESPN (nov/2026), cortadas para o essencial, em tests/Fixtures/espn.
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $fixture = fn (string $name) => json_decode(file_get_contents(base_path("tests/Fixtures/espn/{$name}.json")), true);
    Http::fake(function (Request $request) use ($fixture) {
        $url = $request->url();

        return match (true) {
            str_ends_with(parse_url($url, PHP_URL_PATH), '/nba/teams') => Http::response($fixture('teams')),
            // Só o Boston tem calendário na fixture; playoffs (seasontype=3) ainda vazios.
            str_contains($url, '/teams/2/schedule') && ! str_contains($url, 'seasontype=3') => Http::response($fixture('schedule_bos')),
            str_contains($url, '/schedule') => Http::response(['events' => []]),
            str_contains($url, '/teams/2/roster') => Http::response($fixture('roster_bos')),
            str_contains($url, '/teams/2/depthcharts') => Http::response($fixture('depth_bos')),
            str_contains($url, '/depthcharts') => Http::response(['depthchart' => []]),
            str_contains($url, '/roster') => Http::response(['athletes' => []]),
            str_contains($url, '/summary') => Http::response($fixture('summary_401909936')),
            default => Http::response([], 404),
        };
    });
    $this->espn = new EspnClient;
});

it('monta o calendário pelos times, com nossas siglas e só a TV nacional', function () {
    $games = collect($this->espn->schedule())->keyBy('id');

    expect($games)->toHaveCount(3)
        ->and($games['401909088'])->toMatchArray([
            'phase' => 'REGULAR', 'status' => 'FINAL', 'start_time' => '2026-10-20T19:00Z',
            'arena' => 'Little Caesars Arena', 'national_tv' => 'NBC',
        ])
        ->and($games['401909088']['home'])->toMatchArray(['id' => 1610612765, 'tricode' => 'DET'])
        ->and($games['401909088']['away'])->toMatchArray(['id' => 1610612738, 'tricode' => 'BOS'])
        // ESPN chama o Knicks de "NY"; para nós é NYK.
        ->and($games['401909095']['away'])->toMatchArray(['id' => 1610612752, 'tricode' => 'NYK'])
        // League Pass é pacote de assinatura do mercado local, não TV nacional.
        ->and($games['401909870']['national_tv'])->toBeNull();
});

it('lê placar, quartos e box score com nomes reais', function () {
    $box = $this->espn->boxScore('401909936');

    expect($box)->toMatchArray(['status' => 'FINAL', 'home_score' => 117, 'away_score' => 104, 'clock' => null])
        ->and($box['periods'])->toBe([
            ['period' => 1, 'home_points' => 33, 'away_points' => 26],
            ['period' => 2, 'home_points' => 30, 'away_points' => 25],
            ['period' => 3, 'home_points' => 33, 'away_points' => 22],
            ['period' => 4, 'home_points' => 21, 'away_points' => 31],
        ]);

    $wagner = collect($box['players'])->firstWhere('full_name', 'Franz Wagner');
    expect($wagner)->toMatchArray([
        'team_id' => 1610612753, 'position' => 'F', 'minutes' => '38:00',
        'pts' => 15, 'fgm' => 7, 'fga' => 13, 'fg3m' => 0, 'ftm' => 1, 'fta' => 4, 'reb' => 8, 'ast' => 15,
    ]);
});

it('lê as jogadas: tipo, resultado, placar e posição do arremesso', function () {
    $plays = collect($this->espn->playByPlay('401909936'))->keyBy('action_number');

    expect($plays->keys()->all())->toBe([4, 9, 10, 12, 15, 695, 696, 697]);

    // Lance livre: sem posição na quadra.
    expect($plays[9])->toMatchArray(['action_type' => 'freethrow', 'shot_result' => 'MADE', 'points' => 1, 'x' => null, 'score_away' => 1]);

    // Arremesso do mandante (PHX): vai para a cesta da direita. 8 pés da linha de fundo, 16 de largura.
    expect($plays[12])->toMatchArray([
        'action_type' => '2pt', 'shot_result' => 'MISSED', 'points' => 0, 'team_id' => 1610612756,
        'x' => round((94 - 8) / 94 * 100, 2), 'y' => 32.0,
    ]);
    expect($plays[15])->toMatchArray(['action_type' => '3pt', 'shot_result' => 'MADE', 'points' => 3, 'score_home' => 3, 'score_away' => 1, 'clock' => '11:04']);

    // O que não é arremesso fica com o tipo legível e sem resultado.
    expect($plays[695])->toMatchArray(['action_type' => 'shot_clock_turnover', 'shot_result' => null, 'x' => null]);

    // Só as novas.
    expect(array_column($this->espn->playByPlay('401909936', 15), 'action_number'))->toBe([695, 696, 697]);
});

it('o placar e as jogadas de uma atualização saem de uma chamada só', function () {
    $this->espn->boxScore('401909936');
    $this->espn->playByPlay('401909936');

    Http::assertSentCount(2); // /teams (tradução dos ids) + /summary
});

it('traz os elencos com o nosso id de time e a posição detalhada do quadro de profundidade', function () {
    $players = collect($this->espn->rosters())->keyBy('full_name');

    expect($players)->toHaveCount(3)
        ->and($players['Devin Carter'])->toBe(['id' => 4433188, 'team_id' => 1610612738, 'full_name' => 'Devin Carter', 'position' => 'G', 'role' => 'PG', 'jersey' => '99'])
        // Reserva de PG, mas titular de SG: fica SG.
        ->and($players['Mike Conley']['role'])->toBe('SG')
        // Fora do quadro: pela posição genérica.
        ->and($players['Luka Garza'])->toMatchArray(['position' => 'C', 'role' => 'C']);
});
