<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRoleRequest extends FormRequest
{
    /** Quem chega aqui já passou pelo role:admin da rota. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Evita que o admin se rebaixe sem querer e o sistema fique sem ninguém com acesso total.
                if ($this->user()->is($this->route('user'))) {
                    $validator->errors()->add('role', 'Você não pode mudar o seu próprio papel.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'role.required' => 'Escolha um papel.',
            'role.enum' => 'Papel inválido. Use admin, moderador ou jogador.',
        ];
    }

    public function role(): UserRole
    {
        return UserRole::from($this->validated('role'));
    }
}
