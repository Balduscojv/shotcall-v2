<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Libera a rota só para os papéis listados: ->middleware('role:moderator').
 * Admin sempre passa, porque tem acesso completo ao sistema.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless($user->isAdmin() || in_array($user->role, $allowed, true), 403, 'Você não tem permissão para isso.');

        return $next($request);
    }
}
