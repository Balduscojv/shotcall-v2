<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'handle' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[a-z0-9_]+$/', 'unique:users,handle'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'handle' => strtolower(ltrim((string) $this->input('handle'), '@')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'handle.regex' => 'Use só letras minúsculas, números e _.',
            'handle.unique' => 'Esse @ já tem dono.',
            'email.unique' => 'Esse e-mail já tem conta. Tente entrar.',
        ];
    }
}
