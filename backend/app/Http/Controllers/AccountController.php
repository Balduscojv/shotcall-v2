<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\Account\AccountDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Configurações da conta: dados, senha e exclusão. */
class AccountController extends Controller
{
    /** PATCH /me { name?, handle?, email? } */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->merge(array_filter([
            'handle' => $request->has('handle') ? strtolower(ltrim((string) $request->input('handle'), '@')) : null,
            'email' => $request->has('email') ? strtolower(trim((string) $request->input('email'))) : null,
        ], fn ($v) => $v !== null));

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'handle' => ['sometimes', 'required', 'string', 'min:3', 'max:24', 'regex:/^[a-z0-9_]+$/', Rule::unique('users', 'handle')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'handle.regex' => 'Use só letras minúsculas, números e _.',
            'handle.unique' => 'Esse @ já tem dono.',
            'email.unique' => 'Esse e-mail já está em outra conta.',
        ]);

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }
        $user->forceFill($data)->save();

        return (new UserResource($user->loadMissing(['equippedBanner', 'equippedFrame', 'equippedTitle'])))->response()->setStatusCode(200);
    }

    /** PUT /me/password { current_password, password, password_confirmation } */
    public function password(Request $request): Response
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'A senha atual não confere.',
            'password.confirmed' => 'A confirmação não bate com a nova senha.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->input('password')])->save();

        // Derruba as outras sessões abertas; esta continua.
        Auth::guard('web')->logoutOtherDevices($request->input('password'));

        return response()->noContent();
    }

    /** DELETE /me { password } — exclui a conta e encerra a sessão. */
    public function destroy(Request $request, AccountDeletion $deletion): Response
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.current_password' => 'Senha incorreta.',
        ]);

        $user = $request->user();
        Auth::guard('web')->logout();
        $deletion->delete($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
