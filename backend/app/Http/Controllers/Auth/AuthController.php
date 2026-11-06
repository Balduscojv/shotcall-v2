<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Cosmetic;
use App\Models\User;
use App\Services\Cosmetics\AwardService;
use App\Services\Leagues\JoinGlobalLeague;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Login por sessão (Sanctum SPA): o Next fala com a API pelo mesmo domínio e o cookie é httpOnly. */
class AuthController extends Controller
{
    public function register(RegisterRequest $request, JoinGlobalLeague $joinGlobal, AwardService $awards): UserResource
    {
        $user = DB::transaction(function () use ($request, $joinGlobal, $awards) {
            $user = User::create($request->validated());
            $joinGlobal($user);
            // Gatilho de cadastro: libera os prêmios de boas-vindas e já deixa equipados.
            $awards->fire($user, Cosmetic::TRIGGER_SIGNUP, equipIfEmpty: true);

            return $user->load(['equippedBanner', 'equippedFrame', 'equippedTitle']);
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function login(LoginRequest $request): UserResource
    {
        $credentials = $request->safe()->only('email', 'password');

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha não conferem.']);
        }

        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        // Status explícito: o Resource devolveria 201 se o model acabou de ser criado na mesma execução.
        $user = $request->user()->loadMissing(['equippedBanner', 'equippedFrame', 'equippedTitle']);

        return (new UserResource($user))->response()->setStatusCode(200);
    }
}
