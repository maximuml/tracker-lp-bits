<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NasToolsApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Legacy: encrypted JSON string
            'data' => 'nullable|string',
            // v2 fields
            'version' => ['nullable', 'string', Rule::in(['v2'])],
            'uid' => 'nullable|integer',
            'passkey' => 'nullable|string|size:32',
            'timestamp' => 'nullable|integer',
            'nonce' => 'nullable|string',
            'signature' => 'nullable|string',
        ];
    }
}
