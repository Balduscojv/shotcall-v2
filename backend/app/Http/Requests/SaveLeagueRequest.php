<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Criar ou editar liga: nome, cor do escudo, descrição, privacidade e competições. */
class SaveLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:40'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['nullable', 'string', 'max:200'],
            'privacy' => ['required', Rule::in(['PRIVATE', 'PUBLIC'])],
            'competitions' => ['required', 'array', 'min:1'],
            'competitions.*' => ['distinct', Rule::in(['FANTASY', 'SHOTCALL'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Dê um nome para a liga.',
            'name.min' => 'O nome precisa de pelo menos 3 letras.',
            'name.max' => 'O nome pode ter até 40 caracteres.',
            'color.*' => 'Escolha uma cor para o escudo.',
            'description.max' => 'A descrição pode ter até 200 caracteres.',
            'privacy.*' => 'Escolha se a liga é privada ou pública.',
            'competitions.required' => 'Escolha pelo menos uma competição.',
            'competitions.min' => 'Escolha pelo menos uma competição.',
            'competitions.*.*' => 'Competição inválida.',
        ];
    }
}
