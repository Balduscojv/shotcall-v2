<?php

namespace App\Http\Requests;

use App\Services\Profile\ProfileImage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $minimum = $this->route('kind') === ProfileImage::AVATAR
            ? 'dimensions:min_width=128,min_height=128'
            : 'dimensions:min_width=600,min_height=140';

        return ['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', $minimum]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.mimes' => 'Use uma imagem JPG, PNG ou WebP.',
            'image.max' => 'A imagem pode ter no máximo 8 MB.',
            'image.dimensions' => $this->route('kind') === ProfileImage::AVATAR
                ? 'A foto precisa ter pelo menos 128 × 128 pixels.'
                : 'O banner precisa ter pelo menos 600 × 140 pixels.',
        ];
    }
}
