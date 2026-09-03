<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Legacy fields (still required for legacy protocol)
            'uid' => 'required|integer',
            'timestamp' => 'required|integer',
            'nonce' => 'required|string',
            'signature' => 'required|string',
            // v2 fields (optional — used when version=v2)
            'version' => ['nullable', 'string', Rule::in(['v2'])],
            'passkey' => 'nullable|string|size:32',
        ];
    }
}
