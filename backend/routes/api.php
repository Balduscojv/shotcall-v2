<?php

use App\Http\Controllers\Admin\CosmeticCatalogController;
use App\Http\Controllers\Admin\MissionController;
use App\Http\Controllers\Admin\UserCosmeticController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CosmeticController;
use App\Http\Controllers\FantasyController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileMediaController;
use App\Http\Controllers\ShotCallController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // Sem 'guest': entrar ou criar conta com uma sessão antiga aberta troca a sessão,
    // em vez de travar a tela de login com "você já está logado".
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);

    // Foto de perfil e banner
    Route::post('me/{kind}', [ProfileMediaController::class, 'update'])->whereIn('kind', ['avatar', 'banner'])->middleware('throttle:20,1');
    Route::delete('me/{kind}', [ProfileMediaController::class, 'destroy'])->whereIn('kind', ['avatar', 'banner']);

    // Calendário e jogos da NBA
    Route::get('games', [GameController::class, 'index']);
    Route::get('games/{game}', [GameController::class, 'show']);
    Route::put('games/{game}/shot-call', [ShotCallController::class, 'update'])->middleware('throttle:30,1');

    // Fantasy: mercado da rodada e meu time
    Route::get('fantasy/market', [FantasyController::class, 'market']);
    Route::get('fantasy/team', [FantasyController::class, 'team']);
    Route::put('fantasy/team', [FantasyController::class, 'save'])->middleware('throttle:30,1');

    // Meus prêmios: coleção e o que está em uso (um por tipo)
    Route::get('me/cosmetics', [CosmeticController::class, 'index']);
    Route::put('me/cosmetics/{slot}', [CosmeticController::class, 'equip'])->whereIn('slot', ['banner', 'frame', 'title']);

    // Perfil por @ e suas abas
    Route::prefix('users/{user:handle}')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'show');
        Route::get('shot-calls', 'shotCalls');
        Route::get('awards', 'awards');
        Route::get('leagues', 'leagues');
        Route::get('friends', 'friends');
    });

    // Amizades
    Route::get('friends/requests', [FriendshipController::class, 'requests']);
    Route::get('friends/search', [FriendshipController::class, 'search'])->middleware('throttle:60,1');
    Route::post('friends', [FriendshipController::class, 'store'])->middleware('throttle:30,1');
    Route::post('friends/{user:handle}/accept', [FriendshipController::class, 'accept']);
    Route::delete('friends/{user:handle}', [FriendshipController::class, 'destroy']);

    // Notificações (sininho)
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read', [NotificationController::class, 'readAll']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->whereUuid('id');

    // Administração — admin tem acesso completo ao sistema e às configurações.
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('users', [UserRoleController::class, 'index']);
        Route::patch('users/{user:handle}/role', [UserRoleController::class, 'update']);
        Route::get('users/{user:handle}/cosmetics', [UserCosmeticController::class, 'index']);
        Route::post('users/{user:handle}/cosmetics', [UserCosmeticController::class, 'store']);
        Route::delete('users/{user:handle}/cosmetics/{cosmetic:key}', [UserCosmeticController::class, 'destroy']);

        // Catálogo de prêmios. Editar é POST porque pode vir com imagem (multipart).
        Route::get('cosmetics', [CosmeticCatalogController::class, 'index']);
        Route::post('cosmetics', [CosmeticCatalogController::class, 'store']);
        Route::post('cosmetics/{cosmetic:key}', [CosmeticCatalogController::class, 'update']);
        Route::delete('cosmetics/{cosmetic:key}', [CosmeticCatalogController::class, 'destroy']);

        // Missões: condição do catálogo + parâmetros + recompensas.
        Route::get('missions/conditions', [MissionController::class, 'conditions']);
        Route::get('missions', [MissionController::class, 'index']);
        Route::post('missions', [MissionController::class, 'store']);
        Route::get('missions/{mission}', [MissionController::class, 'show'])->whereNumber('mission');
        Route::put('missions/{mission}', [MissionController::class, 'update'])->whereNumber('mission');
        Route::patch('missions/{mission}/active', [MissionController::class, 'toggle'])->whereNumber('mission');
        Route::delete('missions/{mission}', [MissionController::class, 'destroy'])->whereNumber('mission');
    });
});
