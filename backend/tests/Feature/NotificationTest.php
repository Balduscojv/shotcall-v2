<?php

use App\Models\Cosmetic;
use App\Models\User;
use App\Services\Cosmetics\AwardService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Notifications\DatabaseNotification;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ana = User::factory()->create(['handle' => 'ana', 'name' => 'Ana']);
    $this->bia = User::factory()->create(['handle' => 'bia', 'name' => 'Bia']);
});

function unreadKinds(User $user): array
{
    return $user->fresh()->unreadNotifications()->pluck('type')->all();
}

it('avisa quem recebe o pedido, e quem pediu quando é aceito', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia'])->assertCreated();

    $this->actingAs($this->bia)->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonPath('data.0.kind', 'friend_request')
        ->assertJsonPath('data.0.body', 'Ana (@ana) quer ser seu amigo.')
        ->assertJsonPath('data.0.url', '/u/bia/amigos')
        ->assertJsonPath('data.0.actor.handle', 'ana')
        ->assertJsonPath('data.0.read_at', null);

    $this->actingAs($this->bia)->postJson('/api/friends/ana/accept')->assertOk();

    // O pedido já foi resolvido: sai das não lidas de quem aceitou.
    expect(unreadKinds($this->bia))->toBe([])
        ->and(unreadKinds($this->ana))->toBe(['friend_accepted']);
    $this->actingAs($this->ana)->getJson('/api/notifications')
        ->assertJsonPath('data.0.url', '/u/bia')
        ->assertJsonPath('data.0.actor.handle', 'bia');
});

it('pedido cancelado ou recusado some do sininho', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);
    $this->actingAs($this->ana)->deleteJson('/api/friends/bia')->assertNoContent();
    expect($this->bia->notifications()->count())->toBe(0);

    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);
    $this->actingAs($this->bia)->deleteJson('/api/friends/ana')->assertNoContent();
    expect($this->bia->notifications()->count())->toBe(0);
});

it('quando os dois se pedem, só avisa o primeiro pedido e o aceite', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);
    $this->actingAs($this->bia)->postJson('/api/friends', ['handle' => 'ana'])->assertJsonPath('data.status', 'FRIENDS');

    expect(unreadKinds($this->bia))->toBe([])
        ->and(unreadKinds($this->ana))->toBe(['friend_accepted']);
});

it('avisa prêmio liberado, mas não os de boas-vindas do cadastro', function () {
    $awards = app(AwardService::class);
    $awards->fire($this->ana, Cosmetic::TRIGGER_SIGNUP, equipIfEmpty: true);
    expect($this->ana->notifications()->count())->toBe(0);

    $clutch = Cosmetic::where('key', 'mission-clutch')->firstOrFail();
    expect($awards->grant($this->ana, $clutch, 'Missão Clutch · LAL @ BOS'))->toBeTrue()
        ->and($awards->grant($this->ana, $clutch, 'de novo'))->toBeFalse();   // já tinha: não repete o aviso

    $this->actingAs($this->ana)->getJson('/api/notifications')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kind', 'award')
        ->assertJsonPath('data.0.title', 'Prêmio liberado: Clutch')
        ->assertJsonPath('data.0.body', 'Título · Raro · Missão Clutch · LAL @ BOS. Equipe na aba Prêmios.')
        ->assertJsonPath('data.0.url', '/u/ana/premios')
        ->assertJsonPath('data.0.actor', null);
});

it('conta as não lidas e marca como lidas', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);
    $carla = User::factory()->create(['handle' => 'carla']);
    $this->actingAs($carla)->postJson('/api/friends', ['handle' => 'bia']);

    $this->actingAs($this->bia)->getJson('/api/notifications/unread-count')->assertJsonPath('data.count', 2);

    $id = $this->bia->notifications()->first()->id;
    $this->actingAs($this->bia)->postJson("/api/notifications/{$id}/read")->assertNoContent();
    $this->actingAs($this->bia)->getJson('/api/notifications/unread-count')->assertJsonPath('data.count', 1);

    $this->actingAs($this->bia)->postJson('/api/notifications/read')->assertNoContent();
    $this->actingAs($this->bia)->getJson('/api/notifications/unread-count')->assertJsonPath('data.count', 0);
    $this->actingAs($this->bia)->getJson('/api/notifications')->assertJsonCount(2, 'data');
});

it('ninguém mexe nas notificações dos outros', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);
    $id = $this->bia->notifications()->first()->id;

    $this->actingAs($this->ana)->postJson("/api/notifications/{$id}/read")->assertNotFound();
    $this->actingAs($this->ana)->getJson('/api/notifications')->assertJsonCount(0, 'data');
    expect(unreadKinds($this->bia))->toBe(['friend_request']);
});

it('as notificações saem junto com o usuário', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);
    $this->bia->delete();

    expect(DatabaseNotification::count())->toBe(0);
});

it('exige login', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});
