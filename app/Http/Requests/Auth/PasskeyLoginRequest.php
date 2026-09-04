<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PasskeyLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'passkey' => 'required|string|regex:/^[a-f0-9]{32}$/i',
            'timestamp' => 'required|integer',
            'signature' => 'required|string|regex:/^[a-f0-9]{64}$/i',
        ];
    }
}
