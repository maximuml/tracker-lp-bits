<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AmmdsApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uid' => 'required|integer',
            'timestamp' => 'required|integer',
            'nonce' => 'required|string',
            'signature' => 'required|string',
        ];
    }
}
