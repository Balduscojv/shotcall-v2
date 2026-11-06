<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFriendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['handle' => ['required', 'string', 'exists:users,handle']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['handle' => strtolower(trim(ltrim((string) $this->input('handle'), '@ ')))]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['handle.exists' => 'Ninguém com esse @ por aqui.'];
    }
}
