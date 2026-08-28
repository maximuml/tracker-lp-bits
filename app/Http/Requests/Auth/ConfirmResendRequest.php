<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmResendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string'],
            'wantpassword' => ['nullable', 'string'],
            'passagain' => ['nullable', 'string'],
            'imagehash' => ['nullable', 'string'],
            'imagestring' => ['nullable', 'string'],
        ];
    }
}
