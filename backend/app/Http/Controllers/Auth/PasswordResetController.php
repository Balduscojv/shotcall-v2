<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/** Esqueci a senha: link por e-mail que abre /redefinir-senha no site. */
class PasswordResetController extends Controller
{
    /** POST /auth/forgot-password { email } */
    public function send(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email']]);

        // A resposta é a mesma com ou sem conta, para não revelar quem está cadastrado.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Se esse e-mail tiver conta, o link para criar uma senha nova chega em instantes.']);
    }

    /** POST /auth/reset-password { token, email, password, password_confirmation } */
    public function reset(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ], ['password.confirmed' => 'A confirmação não bate com a nova senha.']);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => $status === Password::RESET_THROTTLED
                    ? 'Muitas tentativas. Espere um minuto.'
                    : 'Esse link expirou ou já foi usado. Peça um novo.',
            ]);
        }

        return response()->json(['message' => 'Senha nova salva. Agora é só entrar.']);
    }
}
