<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Confirmação de e-mail. O link do e-mail abre /verificar-email no site, que repassa os
 * parâmetros para cá. A assinatura é da URL relativa, porque a chamada chega pelo proxy do Next.
 */
class EmailVerificationController extends Controller
{
    /** GET /auth/verify-email/{id}/{hash}?expires=&signature= */
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        abort_unless($request->hasValidSignature(absolute: false), 403, 'Esse link expirou. Peça um novo no site.');

        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Esse link não é mais válido.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json(['message' => 'E-mail confirmado.']);
    }

    /** POST /me/verification-email — manda o link de novo. */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Seu e-mail já está confirmado.']);
        }
        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Link enviado. Confira a caixa de entrada (e o spam).']);
    }
}
