<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserMedalUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'price' => 'required|integer|min:1',
            'image_large' => 'required|url',
            'image_small' => 'required|url',
            'duration' => 'nullable|integer|min:-1',
        ];
    }
}
