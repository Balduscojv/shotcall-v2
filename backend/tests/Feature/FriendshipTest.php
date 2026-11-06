<?php

use App\Models\Friendship;
use App\Models\League;
use App\Models\User;
use App\Services\Missions\MissionRunner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ana = User::factory()->create(['handle' => 'ana']);
    $this->bia = User::factory()->create(['handle' => 'bia']);
});

/** Liga com os membros dados; finalizada ou não. */
function leagueWith(array $members, bool $finished = true, bool $global = false): League
{
    $league = League::create([
        'slug' => Str::random(8), 'name' => 'Liga '.Str::random(4), 'color' => '#ff6b00', 'owner_id' => $members[0]->id,
        'invite_code' => Str::upper(Str::random(6)), 'competitions' => ['FANTASY'], 'is_global' => $global,
        'finished_at' => $finished ? now() : null,
    ]);
    $league->members()->attach(collect($members)->pluck('id'));

    return $league;
}

function befriend(User $a, User $b): void
{
    Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => Friendship::ACCEPTED, 'accepted_at' => now()]);
}

function splashOwners(): array
{
    return User::whereHas('cosmetics', fn ($q) => $q->where('key', 'mission-splash-brothers'))->orderBy('handle')->pluck('handle')->all();
}

// Busca pelo @

it('busca pelo começo do @ e mostra a relação com cada um', function () {
    User::factory()->create(['handle' => 'bianca']);
    User::factory()->create(['handle' => 'carla']);
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);

    $this->actingAs($this->ana)->getJson('/api/friends/search?q=@BI')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.handle', 'bia')
        ->assertJsonPath('data.0.friendship', 'SENT')
        ->assertJsonPath('data.1.handle', 'bianca')
        ->assertJsonPath('data.1.friendship', 'NONE');

    $this->actingAs($this->bia)->getJson('/api/friends/search?q=an')
        ->assertJsonPath('data.0.friendship', 'RECEIVED');
});

it('não se acha na busca e ignora termo curto', function () {
    $this->actingAs($this->ana)->getJson('/api/friends/search?q=an')->assertJsonCount(0, 'data');
    $this->actingAs($this->ana)->getJson('/api/friends/search?q=b')->assertJsonCount(0, 'data');
});

it('trata o _ do @ como letra, não como curinga', function () {
    User::factory()->create(['handle' => 'crava_tudo']);
    User::factory()->create(['handle' => 'cravaxtudo']);

    $this->actingAs($this->ana)->getJson('/api/friends/search?q=crava_')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.handle', 'crava_tudo');
});

it('não deixa se adicionar nem adicionar @ que não existe', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'ana'])->assertJsonValidationErrors('handle');
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'ninguem'])->assertJsonValidationErrors('handle');
});

// Splash Brothers

it('esconde o Splash Brothers como ??? com a dica', function () {
    $this->actingAs($this->ana)->getJson('/api/me/cosmetics')
        ->assertJsonFragment(['hint' => 'Forme amizades consistentes.', 'rarity' => 'RARE', 'slot' => 'TITLE']);
});

it('libera o Splash Brothers para os dois amigos quando finalizam a 3ª liga juntos', function () {
    befriend($this->ana, $this->bia);
    leagueWith([$this->ana, $this->bia]);
    leagueWith([$this->ana, $this->bia]);
    $third = leagueWith([$this->ana, $this->bia], finished: false);

    $mission = app(MissionRunner::class);
    expect($mission->leagueFinished($third))->toBe(0)->and(splashOwners())->toBe([]);

    $third->update(['finished_at' => now()]);
    expect($mission->leagueFinished($third))->toBe(2)
        ->and(splashOwners())->toBe(['ana', 'bia'])
        ->and($this->ana->cosmetics()->where('key', 'mission-splash-brothers')->first()->pivot->unlock_reason)
        ->toBe('Splash Brothers com @bia');
});

it('não libera para quem não é amigo, nem conta liga global ou não finalizada', function () {
    leagueWith([$this->ana, $this->bia]);
    leagueWith([$this->ana, $this->bia]);
    $third = leagueWith([$this->ana, $this->bia]);
    expect(app(MissionRunner::class)->leagueFinished($third))->toBe(0);   // não são amigos

    befriend($this->ana, $this->bia);
    $third->members()->detach($this->bia->id);
    leagueWith([$this->ana, $this->bia], global: true);
    leagueWith([$this->ana, $this->bia], finished: false);
    expect(app(MissionRunner::class)->friendshipAccepted($this->ana, $this->bia))->toBe(0)
        ->and(splashOwners())->toBe([]);
});

it('libera ao aceitar a amizade de quem já finalizou 3 ligas junto', function () {
    foreach (range(1, 3) as $_) {
        leagueWith([$this->ana, $this->bia]);
    }

    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);
    expect(splashOwners())->toBe([]);

    $this->actingAs($this->bia)->postJson('/api/friends/ana/accept')->assertOk();
    expect(splashOwners())->toBe(['ana', 'bia']);
});
