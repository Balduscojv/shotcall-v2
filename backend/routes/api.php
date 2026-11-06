<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileMediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware(['guest', 'throttle:10,1'])->group(function () {
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
    Route::post('friends', [FriendshipController::class, 'store'])->middleware('throttle:30,1');
    Route::post('friends/{user:handle}/accept', [FriendshipController::class, 'accept']);
    Route::delete('friends/{user:handle}', [FriendshipController::class, 'destroy']);
});
