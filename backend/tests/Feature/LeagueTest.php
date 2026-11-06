<?php

use App\Models\Cosmetic;
use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\GameShotCall;
use App\Models\League;
use App\Models\LeagueStanding;
use App\Models\LeagueTrophy;
use App\Models\Mission;
use App\Models\Player;
use App\Models\PlayerGameStat;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\UserAccuracy;
use App\Services\Leagues\JoinGlobalLeague;
use App\Services\Leagues\LeagueRanking;
use App\Services\Leagues\LeagueSeasonCloser;
use App\Services\Leagues\LeagueService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

// Sexta, 6/nov: a rodada 3 (2 a 8/nov) está rolando; a 1 e a 2 (outubro) já foram apuradas.
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-06 12:00', 'America/New_York'));
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');

    $this->ana = User::factory()->create(['handle' => 'ana', 'name' => 'Ana']);
    $this->bia = User::factory()->create(['handle' => 'bia', 'name' => 'Bia']);
    $this->caio = User::factory()->create(['handle' => 'caio', 'name' => 'Caio']);
    foreach ([$this->ana, $this->bia, $this->caio] as $user) {
        app(JoinGlobalLeague::class)($user);
    }
    $this->round = fn (int $n) => Round::where('number', $n)->first();
});

afterEach(fn () => Carbon::setTestNow());

function createLeague(User $owner, array $data = []): League
{
    $slug = test()->actingAs($owner)->postJson('/api/leagues', $data + [
        'name' => 'Liga do Rachão', 'color' => '#8B5CF6', 'privacy' => 'PRIVATE', 'competitions' => ['FANTASY', 'SHOTCALL'],
    ])->assertCreated()->json('data.slug');

    return League::where('slug', $slug)->first();
}

/** Membro que pontua desde a rodada 1 (as ligas de teste "existem" desde o começo da temporada). */
function addMember(League $league, User $user, string $role = 'MEMBER', int $from = 1): void
{
    $league->members()->syncWithoutDetaching([$user->id => ['role' => $role, 'joined_round_number' => $from]]);
}

function team(User $user, int $round, float $points, float $patrimony = 100): void
{
    FantasyTeam::create([
        'user_id' => $user->id, 'round_id' => Round::where('number', $round)->value('id'),
        'balance' => 0, 'patrimony' => $patrimony, 'points' => $points,
    ]);
}

it('cria a liga com o dono, código de convite e pontuando a partir da próxima rodada', function () {
    $league = createLeague($this->ana);

    expect($league->owner_id)->toBe($this->ana->id)
        ->and($league->invite_code)->toMatch('/^[A-HJ-NP-Z2-9]{6}$/')
        ->and($league->max_members)->toBe(20)
        ->and($league->members()->first()->pivot->role)->toBe('OWNER')
        // a rodada 3 já começou: conta a partir da 4
        ->and($league->members()->first()->pivot->joined_round_number)->toBe(4);

    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}")
        ->assertJsonPath('data.role', 'OWNER')
        ->assertJsonPath('data.invite_code', $league->invite_code)
        ->assertJsonPath('data.can.update', true)
        ->assertJsonPath('data.can.leave', false);
});

it('valida a criação', function (array $data, string $field) {
    $this->actingAs($this->ana)->postJson('/api/leagues', $data + [
        'name' => 'Liga', 'color' => '#8B5CF6', 'privacy' => 'PRIVATE', 'competitions' => ['FANTASY'],
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'nome curto' => [['name' => 'ab'], 'name'],
    'cor' => [['color' => 'roxo'], 'color'],
    'sem competição' => [['competitions' => []], 'competitions'],
    'competição inventada' => [['competitions' => ['XADREZ']], 'competitions.0'],
]);

it('entra pelo código, mas não em liga cheia', function () {
    $league = createLeague($this->ana);

    $this->actingAs($this->bia)->getJson("/api/leagues/invite/{$league->invite_code}")
        ->assertJsonPath('data.name', 'Liga do Rachão')->assertJsonPath('data.is_member', false);
    $this->actingAs($this->bia)->postJson('/api/leagues/join', ['code' => strtolower($league->invite_code)])
        ->assertOk()->assertJsonPath('data.slug', $league->slug);
    expect($league->hasMember($this->bia))->toBeTrue();

    $this->actingAs($this->caio)->postJson('/api/leagues/join', ['code' => 'ZZZZZZ'])->assertJsonValidationErrors('code');

    $league->update(['max_members' => 2]);
    $this->actingAs($this->caio)->postJson('/api/leagues/join', ['code' => $league->invite_code])
        ->assertJsonValidationErrors(['code' => 'Essa liga está cheia.']);
});

it('limita a 10 ligas por usuário, sem contar a global', function () {
    foreach (range(1, 10) as $i) {
        // Pelo serviço: pela API, a 11ª esbarraria no limite de 10 criações por minuto.
        app(LeagueService::class)->create($this->ana, ['name' => "Liga {$i}", 'color' => '#8B5CF6', 'privacy' => 'PRIVATE', 'competitions' => ['FANTASY']]);
    }
    $this->actingAs($this->ana)->getJson('/api/leagues')->assertJsonCount(11, 'data')->assertJsonPath('meta.count', 10);

    $this->actingAs($this->ana)->postJson('/api/leagues', [
        'name' => 'Liga 11', 'color' => '#8B5CF6', 'privacy' => 'PRIVATE', 'competitions' => ['FANTASY'],
    ])->assertJsonValidationErrors('league');
});

it('liga privada só aparece para membros; pública aparece na busca e aceita entrar direto', function () {
    $private = createLeague($this->ana, ['name' => 'Segredo']);
    $public = createLeague($this->ana, ['name' => 'Aberta do Bairro', 'privacy' => 'PUBLIC']);

    $this->actingAs($this->bia)->getJson("/api/leagues/{$private->slug}")->assertNotFound();
    $this->actingAs($this->bia)->getJson("/api/leagues/{$private->slug}/standings")->assertNotFound();
    $this->actingAs($this->bia)->postJson("/api/leagues/{$private->slug}/join")->assertJsonValidationErrors('code');

    $this->actingAs($this->bia)->getJson('/api/leagues/search?q=bairro')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $public->slug);
    $this->actingAs($this->bia)->getJson("/api/leagues/{$public->slug}")->assertJsonPath('data.can.join', true);
    $this->actingAs($this->bia)->postJson("/api/leagues/{$public->slug}/join")->assertOk();
    expect($public->hasMember($this->bia))->toBeTrue();
});

it('papéis: só o dono edita; admin remove membro comum, mas não o dono nem outro admin', function () {
    $league = createLeague($this->ana);
    addMember($league, $this->bia, 'ADMIN');
    addMember($league, $this->caio);
    $dani = User::factory()->create(['handle' => 'dani']);
    addMember($league, $dani, 'ADMIN');

    $edit = ['name' => 'Novo nome', 'color' => '#22D3EE', 'privacy' => 'PUBLIC', 'competitions' => ['SHOTCALL']];
    $this->actingAs($this->bia)->patchJson("/api/leagues/{$league->slug}", $edit)->assertForbidden();
    $this->actingAs($this->ana)->patchJson("/api/leagues/{$league->slug}", $edit)->assertJsonPath('data.name', 'Novo nome');

    $this->actingAs($this->bia)->deleteJson("/api/leagues/{$league->slug}/members/ana")->assertForbidden();
    $this->actingAs($this->bia)->deleteJson("/api/leagues/{$league->slug}/members/dani")->assertForbidden();
    $this->actingAs($this->caio)->deleteJson("/api/leagues/{$league->slug}/members/bia")->assertForbidden();
    $this->actingAs($this->bia)->deleteJson("/api/leagues/{$league->slug}/members/caio")->assertNoContent();
    expect($league->hasMember($this->caio))->toBeFalse();

    // Código novo: o antigo para de valer.
    $old = $league->invite_code;
    $this->actingAs($this->bia)->postJson("/api/leagues/{$league->slug}/invite-code")->assertOk();
    $this->actingAs($this->caio)->postJson('/api/leagues/join', ['code' => $old])->assertJsonValidationErrors('code');
});

it('o dono transfere a liga e só então pode sair', function () {
    $league = createLeague($this->ana);
    addMember($league, $this->bia);

    $this->actingAs($this->ana)->postJson("/api/leagues/{$league->slug}/leave")->assertJsonValidationErrors('league');
    $this->actingAs($this->bia)->patchJson("/api/leagues/{$league->slug}/members/bia", ['role' => 'OWNER'])->assertForbidden();
    $this->actingAs($this->ana)->patchJson("/api/leagues/{$league->slug}/members/bia", ['role' => 'OWNER'])->assertNoContent();

    expect($league->fresh()->owner_id)->toBe($this->bia->id)
        ->and($league->members()->whereKey($this->ana->id)->first()->pivot->role)->toBe('ADMIN');

    $this->actingAs($this->ana)->postJson("/api/leagues/{$league->slug}/leave")->assertNoContent();
    $this->actingAs($this->ana)->postJson('/api/leagues/global/leave')->assertJsonValidationErrors('league');
});

it('ranking de fantasy: soma do período, a partir da entrada, desempate pelo patrimônio', function () {
    $league = createLeague($this->ana);
    addMember($league, $this->ana, 'OWNER');
    addMember($league, $this->bia);
    addMember($league, $this->caio, 'MEMBER', from: 2);

    team($this->ana, 1, 50, 100);
    team($this->ana, 2, 30, 104);
    team($this->bia, 1, 20, 100);
    team($this->bia, 2, 60, 110);
    team($this->caio, 1, 300, 100); // antes de entrar: não conta
    team($this->caio, 2, 80, 101);

    // Temporada até a rodada 2: os três com 80 → desempate pelo patrimônio da rodada 2 (110, 104, 101).
    $res = $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?board=fantasy&period=season&round=2")->assertOk();
    expect(collect($res->json('data'))->map(fn ($r) => [$r['user']['handle'], $r['rank'], $r['score']])->all())
        ->toBe([['bia', 1, 80], ['ana', 2, 80], ['caio', 3, 80]])
        // Na rodada 1 (temporada até ela) era Ana 1ª, Bia 2ª, Caio 3º com 0.
        ->and(collect($res->json('data'))->pluck('move', 'user.handle')->all())->toBe(['bia' => 1, 'ana' => -1, 'caio' => 0])
        ->and($res->json('meta.me.rank'))->toBe(2)
        ->and($res->json('meta.me.is_me'))->toBeTrue()
        ->and($res->json('meta.boards'))->toBe(['fantasy', 'patrimony', 'shotcall']);

    // Só a rodada 2.
    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?board=fantasy&period=round&round=2")
        ->assertJsonPath('data.0.user.handle', 'caio')->assertJsonPath('data.0.score', 80);

    // Sem escolher, mostra a rodada que está rolando (a 3), com a parcial.
    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?period=round")
        ->assertJsonPath('meta.round.number', 3)->assertJsonPath('meta.round.month', 'novembro')
        ->assertJsonPath('meta.round.provisional', true);
    // Mês de outubro, até a rodada 2.
    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?period=month&round=2")
        ->assertJsonPath('meta.round.month', 'outubro')->assertJsonPath('meta.round.provisional', false);
});

it('ranking de patrimônio e de shot calls, com desempate pela taxa de acerto', function () {
    $league = createLeague($this->ana, ['competitions' => ['SHOTCALL']]);
    addMember($league, $this->ana, 'OWNER');
    addMember($league, $this->bia);

    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?board=patrimony")
        ->assertJsonPath('meta.boards', ['shotcall'])->assertJsonPath('meta.board', 'shotcall');

    $r3 = ($this->round)(3);
    $bos = Team::where('tricode', 'BOS')->first();
    $lal = Team::where('tricode', 'LAL')->first();
    $game = Game::create([
        'id' => '401000777', 'season_id' => $r3->season_id, 'round_id' => $r3->id, 'phase' => 'REGULAR',
        'home_team_id' => $bos->id, 'away_team_id' => $lal->id, 'start_time' => now()->subDay(), 'status' => 'FINAL',
    ]);
    foreach ([[$this->ana, 5], [$this->bia, 5]] as [$user, $points]) {
        GameShotCall::create([
            'user_id' => $user->id, 'game_id' => $game->id, 'winner_team_id' => $bos->id, 'margin_band' => 'M1_5',
            'mvp_player_id' => 1, 'status' => 'SETTLED', 'points' => $points,
        ]);
    }
    UserAccuracy::create(['user_id' => $this->ana->id, 'mode' => 'GAME', 'rate' => 0.5, 'counted' => 10]);
    UserAccuracy::create(['user_id' => $this->bia->id, 'mode' => 'GAME', 'rate' => 0.7, 'counted' => 10]);

    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?board=shotcall&period=month")
        ->assertJsonPath('meta.round.number', 3)
        ->assertJsonPath('meta.round.month', 'novembro')
        ->assertJsonPath('data.0.user.handle', 'bia')
        ->assertJsonPath('data.1.rank', 2);

    $fantasy = createLeague($this->bia, ['name' => 'Cartoleiros', 'competitions' => ['FANTASY']]);
    addMember($fantasy, $this->ana);
    team($this->ana, 3, 0, 120);
    $this->actingAs($this->ana)->getJson("/api/leagues/{$fantasy->slug}/standings?board=patrimony")
        ->assertJsonPath('meta.period', 'round')
        ->assertJsonPath('data.0.user.handle', 'ana')->assertJsonPath('data.0.score', 120)
        // sem time: fica no fim, sem número
        ->assertJsonPath('data.1.score', null);
});

it('a apuração guarda a posição de cada liga, que aparece em minhas ligas', function () {
    $league = createLeague($this->ana);
    addMember($league, $this->ana, 'OWNER');
    addMember($league, $this->bia);
    team($this->ana, 2, 10);
    team($this->bia, 2, 40);

    app(LeagueRanking::class)->snapshot(($this->round)(2));

    expect(LeagueStanding::where('league_id', $league->id)->where('user_id', $this->ana->id)->value('rank'))->toBe(2);
    $this->actingAs($this->ana)->getJson('/api/leagues')
        ->assertJsonPath('data.0.is_global', true)
        ->assertJsonPath('data.1.slug', $league->slug)
        ->assertJsonPath('data.1.rank', 2);
});

it('membros vêm com o papel, dono primeiro', function () {
    $league = createLeague($this->ana);
    addMember($league, $this->caio);
    addMember($league, $this->bia, 'ADMIN');

    $this->actingAs($this->caio)->getJson("/api/leagues/{$league->slug}/members")
        ->assertJsonPath('data.0.handle', 'ana')->assertJsonPath('data.0.league_role', 'OWNER')
        ->assertJsonPath('data.1.handle', 'bia')->assertJsonPath('data.2.league_role', 'MEMBER');
});

it('rodada em andamento: parcial ao vivo do fantasy (com boost) e dos shot calls', function () {
    // Cache que serializa, como o de verdade: a parcial fica guardada entre as requisições.
    config(['cache.default' => 'file']);
    Cache::flush();

    $league = createLeague($this->ana);
    addMember($league, $this->ana, 'OWNER');
    addMember($league, $this->bia);
    addMember($league, $this->caio, 'MEMBER', from: 4); // entrou com a rodada 3 rolando: não conta

    $r3 = ($this->round)(3); // LOCKED: começou e não foi apurada
    $bos = Team::where('tricode', 'BOS')->first();
    $lal = Team::where('tricode', 'LAL')->first();
    Player::create(['id' => 1, 'full_name' => 'Jogador 1', 'active' => true, 'team_id' => $bos->id, 'position' => 'G', 'role' => 'PG']);
    Player::create(['id' => 2, 'full_name' => 'Jogador 2', 'active' => true, 'team_id' => $lal->id, 'position' => 'G', 'role' => 'SG']);
    $game = fn (string $id, string $status, array $extra = []) => Game::create([
        'id' => $id, 'season_id' => $r3->season_id, 'round_id' => $r3->id, 'phase' => 'REGULAR',
        'home_team_id' => $bos->id, 'away_team_id' => $lal->id, 'start_time' => now()->subHours(2), 'status' => $status,
    ] + $extra);

    // Jogo encerrado e apurado: Ana acertou o vencedor (1 ponto, boost de 1% no fantasy).
    $done = $game('401000801', 'FINAL');
    GameShotCall::create([
        'user_id' => $this->ana->id, 'game_id' => $done->id, 'winner_team_id' => $bos->id, 'margin_band' => 'M16_PLUS',
        'mvp_player_id' => 2, 'status' => 'SETTLED', 'correct' => 1, 'points' => 1,
        'markets' => ['winner' => true, 'margin' => false, 'mvp' => false],
    ]);

    // Ao vivo: BOS 50 × 47 LAL; Jogador 1 com 10 pontos lidera, Jogador 2 com 4.
    $live = $game('401000802', 'LIVE', ['home_score' => 50, 'away_score' => 47]);
    PlayerGameStat::create(['game_id' => $live->id, 'player_id' => 1, 'team_id' => $bos->id, 'pts' => 10]);
    PlayerGameStat::create(['game_id' => $live->id, 'player_id' => 2, 'team_id' => $lal->id, 'pts' => 4]);
    // Bia cravou tudo "se acabasse agora": BOS, 1 a 5, MVP Jogador 1 = 1 + 2 + 3.
    GameShotCall::create([
        'user_id' => $this->bia->id, 'game_id' => $live->id, 'winner_team_id' => $bos->id, 'margin_band' => 'M1_5',
        'mvp_player_id' => 1, 'status' => 'LOCKED',
    ]);

    $lineup = function (User $user, array $players, int $cestinha) use ($r3) {
        $team = FantasyTeam::create(['user_id' => $user->id, 'round_id' => $r3->id, 'cestinha_id' => $cestinha, 'balance' => 0, 'patrimony' => 100]);
        foreach ($players as $slot => $id) {
            $team->players()->create(['slot' => $slot, 'player_id' => $id, 'bought_at_price' => 10]);
        }
    };
    $lineup($this->ana, ['PG' => 1, 'SG' => 2], 1); // 10 × 2 + 4 = 24, × 1,01
    $lineup($this->bia, ['SG' => 2], 2);            // 4 × 2 = 8
    $lineup($this->caio, ['PG' => 1], 1);           // 20, mas não conta

    // Sem escolher rodada, mostra a que está rolando.
    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?board=fantasy&period=round")
        ->assertJsonPath('meta.round.number', 3)
        ->assertJsonPath('meta.round.provisional', true)
        ->assertJsonPath('meta.round.live', true)
        ->assertJsonPath('data.0.user.handle', 'ana')->assertJsonPath('data.0.score', 24.24)
        ->assertJsonPath('data.1.user.handle', 'bia')->assertJsonPath('data.1.score', 8)
        ->assertJsonPath('data.2.user.handle', 'caio')->assertJsonPath('data.2.score', 0);

    // De novo, agora saindo do cache.
    $this->actingAs($this->bia)->getJson("/api/leagues/{$league->slug}/standings?board=fantasy&period=season")
        ->assertOk()->assertJsonPath('data.0.score', 24.24);

    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?board=shotcall&period=season")
        ->assertJsonPath('data.0.user.handle', 'bia')->assertJsonPath('data.0.score', 6)
        ->assertJsonPath('data.1.user.handle', 'ana')->assertJsonPath('data.1.score', 1);

    // Patrimônio não tem parcial.
    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?board=patrimony")
        ->assertJsonPath('meta.round.provisional', false)->assertJsonPath('meta.round.live', false);
});

/** Liga de fantasy com N membros pontuando na rodada 2: o primeiro faz N×10, o segundo (N-1)×10... */
function scoredLeague(User $owner, int $size, array $data = []): array
{
    $league = createLeague($owner, $data + ['competitions' => ['FANTASY']]);
    $users = [$owner, ...User::factory()->count($size - 1)->create()->all()];
    foreach ($users as $i => $user) {
        addMember($league, $user, $i === 0 ? 'OWNER' : 'MEMBER');
        team($user, 2, ($size - $i) * 10);
    }

    return [$league, $users];
}

it('fim de temporada: pódio vira troféu, campeão de liga com 5+ ganha o título, todos são avisados', function () {
    [$league, $users] = scoredLeague($this->ana, 6);
    [$small, $smallUsers] = scoredLeague($this->bia, 4, ['name' => 'Liga Pequena']);

    $this->artisan('leagues:finish', ['season' => '2026-27'])->expectsOutputToContain('2 liga(s) finalizada(s).')->assertSuccessful();

    expect($league->fresh()->finished_at)->not->toBeNull()
        ->and(LeagueTrophy::where('league_id', $league->id)->orderBy('rank')->pluck('rank', 'user_id')->all())
        ->toBe([$users[0]->id => 1, $users[1]->id => 2, $users[2]->id => 3]);

    // Campeã com 6 membros: título com o nome da liga no motivo. Pódio pede 10: ninguém leva a moldura.
    $title = $this->ana->cosmetics()->where('key', 'league-champion')->first();
    expect($title?->pivot->unlock_reason)->toBe('Campeão da Liga do Rachão · Fantasy 2026-27')
        ->and($users[1]->cosmetics()->where('key', 'league-podium')->exists())->toBeFalse()
        // Liga de 4: tem troféu, mas não dá o título.
        ->and(LeagueTrophy::where('league_id', $small->id)->where('rank', 1)->value('user_id'))->toBe($this->bia->id)
        ->and($this->bia->cosmetics()->where('key', 'league-champion')->exists())->toBeFalse();

    $notice = $users[1]->notifications()->where('type', 'league_finished')->first();
    expect($notice->data['body'])->toBe('Temporada 2026-27 encerrada. Você terminou em 2º de 6 no fantasy.')
        ->and($this->ana->notifications()->where('type', 'league_finished')->first()->data['title'])->toBe('Campeão da Liga do Rachão!');

    // Rodar de novo não muda nada.
    $this->artisan('leagues:finish', ['season' => '2026-27'])->expectsOutputToContain('0 liga(s) finalizada(s).');
});

it('pódio da temporada: top 3 de liga com 10+ ganha a moldura', function () {
    [$league, $users] = scoredLeague($this->ana, 10);
    app(LeagueSeasonCloser::class)->closeSeason(Season::first());

    expect(collect($users)->filter(fn (User $u) => $u->cosmetics()->where('key', 'league-podium')->exists())->keys()->all())
        ->toBe([0, 1, 2])
        ->and($users[2]->cosmetics()->where('key', 'league-podium')->first()->pivot->unlock_reason)
        ->toBe('3º na Liga do Rachão · Fantasy 2026-27');
});

it('liga encerrada fica congelada e vira histórico no perfil', function () {
    [$league, $users] = scoredLeague($this->ana, 5);
    addMember($league, $this->bia);
    app(LeagueSeasonCloser::class)->closeSeason(Season::first());

    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}")
        ->assertJsonPath('data.can.update', false)->assertJsonPath('data.can.delete', true)
        ->assertJsonMissingPath('data.invite_code');
    $this->actingAs($this->bia)->postJson("/api/leagues/{$league->slug}/leave")->assertJsonValidationErrors('league');
    $this->actingAs($this->caio)->postJson('/api/leagues/join', ['code' => $league->invite_code])->assertJsonValidationErrors('code');
    $this->actingAs($this->ana)->patchJson("/api/leagues/{$league->slug}", [
        'name' => 'Outra', 'color' => '#22D3EE', 'privacy' => 'PUBLIC', 'competitions' => ['FANTASY'],
    ])->assertForbidden();

    // Ranking parado na última rodada apurada (aqui, a 2, com a 3 rolando; no fim da temporada de
    // verdade, a última), sem parcial.
    $standings = $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings")->assertOk();
    expect($standings->json('meta.round.number'))->toBe(2)
        ->and($standings->json('meta.round.provisional'))->toBeFalse()
        ->and($standings->json('data.0.user.handle'))->toBe('ana');

    // Não ocupa vaga entre as 10.
    $this->actingAs($this->ana)->getJson('/api/leagues')->assertJsonPath('meta.count', 0);

    // Vitrine: o troféu aparece para todos; o link da liga privada, só para quem é membro.
    $this->actingAs($this->bia)->getJson('/api/users/ana')
        ->assertJsonPath('data.trophies.0.rank', 1)
        ->assertJsonPath('data.trophies.0.league_name', 'Liga do Rachão')
        ->assertJsonPath('data.trophies.0.league_slug', $league->slug);
    $this->actingAs($this->caio)->getJson('/api/users/ana')
        ->assertJsonPath('data.trophies.0.league_slug', null);
});

it('sem pontos antes, não mostra subida nem queda', function () {
    $league = createLeague($this->ana);
    addMember($league, $this->ana, 'OWNER');
    addMember($league, $this->bia);
    team($this->ana, 2, 10);
    team($this->bia, 2, 40);

    // Na rodada 1 ninguém pontuou: todos estavam empatados em 1º.
    $this->actingAs($this->ana)->getJson("/api/leagues/{$league->slug}/standings?board=fantasy&period=season&round=2")
        ->assertJsonPath('data.0.move', null)->assertJsonPath('data.1.move', null);
});

it('liga global: sem limite e com todo usuário do sistema, menos a conta de sistema', function () {
    $global = League::where('is_global', true)->first();
    $system = User::where('handle', 'shotcall')->first();

    expect($global->max_members)->toBeNull()
        ->and($global->isFull())->toBeFalse()
        // Criado fora do cadastro (factory): entra do mesmo jeito.
        ->and($global->hasMember(User::factory()->create()))->toBeTrue()
        ->and($global->members()->count())->toBe(User::count() - 1)
        ->and($global->hasMember($system))->toBeFalse();

    $this->actingAs($this->ana)->getJson('/api/leagues/global')
        ->assertJsonPath('data.max_members', null)->assertJsonPath('data.members_count', User::count() - 1);

    // Pelo cadastro, entra uma vez só.
    $this->postJson('/api/auth/register', [
        'name' => 'Dani', 'handle' => 'dani', 'email' => 'dani@exemplo.com', 'password' => 'senha-forte-123',
    ])->assertCreated();
    expect($global->members()->where('handle', 'dani')->count())->toBe(1);

});

/** Missão de liga com o Capuz do Ceifador de recompensa (para não misturar com as do seeder). */
function leagueMission(string $condition, array $params): Mission
{
    $mission = Mission::create([
        'key' => "teste-{$condition}-".uniqid(), 'name' => 'Missão de teste', 'condition' => $condition,
        'params' => $params, 'secret' => false, 'active' => true,
    ]);
    $mission->rewards()->attach(Cosmetic::where('key', 'frame-reaper')->value('id'));

    return $mission;
}

$reaper = fn (User $user) => $user->cosmetics()->where('key', 'frame-reaper')->first()?->pivot->unlock_reason;

it('prêmio vinculado a uma liga específica e a uma competição', function () use ($reaper) {
    [$sponsored] = scoredLeague($this->ana, 5, ['name' => 'Liga Patrocinada']);
    [$other, $otherUsers] = scoredLeague($this->bia, 5, ['name' => 'Outra Liga']);
    leagueMission('league_rank', ['max_rank' => 1, 'min_members' => 5, 'competition' => 0, 'league_id' => $sponsored->id]);
    // Só shot calls: ninguém pontuou em shot calls, então não sai.
    $shotcallOnly = leagueMission('league_rank', ['max_rank' => 1, 'min_members' => 2, 'competition' => 2, 'league_id' => 0]);

    app(LeagueSeasonCloser::class)->closeSeason(Season::first());

    expect($reaper($this->ana))->toBe('Campeão da Liga Patrocinada · Fantasy 2026-27')
        ->and($reaper($this->bia))->toBeNull();
});

it('contagem de vitórias: tricampeão só na terceira liga ganha', function () use ($reaper) {
    leagueMission('league_wins', ['min_wins' => 3, 'max_rank' => 1, 'min_members' => 5, 'competition' => 0]);
    $closer = app(LeagueSeasonCloser::class);
    $season = Season::first();
    $round2 = ($this->round)(2);

    foreach (['Liga A', 'Liga B', 'Liga C'] as $i => $name) {
        // Cada liga com membros novos; Ana entra em todas e faz a maior pontuação.
        [$league, $users] = scoredLeague(User::factory()->create(), 5, ['name' => $name]);
        addMember($league, $this->ana);
        FantasyTeam::updateOrCreate(['user_id' => $this->ana->id, 'round_id' => $round2->id], ['balance' => 0, 'patrimony' => 100, 'points' => 999]);
        $closer->close($league->fresh(), $season, $round2);

        expect($reaper($this->ana))->toBe($i < 2 ? null : '3 títulos de liga')
            // O dono de cada liga ficou em 2º: pódio, não título.
            ->and($reaper($users[0]))->toBeNull();
    }
});

it('prêmio de participação: terminou a temporada na liga patrocinada', function () use ($reaper) {
    [$sponsored, $users] = scoredLeague($this->ana, 3, ['name' => 'Liga Patrocinada']);
    leagueMission('league_season', ['league_id' => $sponsored->id, 'min_members' => 2]);

    app(LeagueSeasonCloser::class)->closeSeason(Season::first());

    expect(collect($users)->every(fn (User $u) => $reaper($u) === 'Temporada completa na Liga Patrocinada'))->toBeTrue();
});

it('moderação: busca a liga, valida o vínculo e mostra o nome na lista', function () {
    $admin = User::factory()->admin()->create();
    $league = createLeague($this->ana, ['name' => 'Liga Patrocinada']);
    $global = League::where('is_global', true)->first();

    $this->actingAs($admin)->getJson('/api/admin/missions/leagues?q=patroc')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $league->id);
    $condition = collect($this->actingAs($admin)->getJson('/api/admin/missions/conditions')->json('data'))->firstWhere('key', 'league_rank');
    expect(collect($condition['params'])->pluck('type', 'name')->all())->toMatchArray(['competition' => 'choice', 'league_id' => 'league']);

    $body = fn (int $leagueId, int $competition = 1) => [
        'name' => 'Campeão Patrocinado', 'condition' => 'league_rank', 'rewards' => ['frame-reaper'], 'secret' => false,
        'params' => ['max_rank' => 1, 'min_members' => 5, 'competition' => $competition, 'league_id' => $leagueId],
    ];
    $this->actingAs($admin)->postJson('/api/admin/missions', $body($global->id))->assertJsonValidationErrors(['params.league_id' => 'Liga não encontrada.']);
    $this->actingAs($admin)->postJson('/api/admin/missions', $body($league->id, 7))->assertJsonValidationErrors('params.competition');
    $this->actingAs($admin)->postJson('/api/admin/missions', $body($league->id))->assertCreated()
        ->assertJsonPath('data.params_display.Liga', 'Liga Patrocinada')
        ->assertJsonPath('data.params_display.Competição', 'Fantasy');
});
