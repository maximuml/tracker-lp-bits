<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for passkey login v2.
 *
 * Required fields:
 * - passkey: 32-char lowercase hex (BitTorrent passkey)
 * - timestamp: unix timestamp (seconds)
 * - nonce: 32-char lowercase hex (unique per request)
 * - signature: 64-char lowercase hex (HMAC-SHA256)
 * - key_id: signing key identifier (alphanumeric, max 32 chars)
 * - action: action scope (default: "login")
 */
class PasskeyLoginV2Request extends FormRequest
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
            'nonce' => 'required|string|regex:/^[a-f0-9]{32}$/i',
            'signature' => 'required|string|regex:/^[a-f0-9]{64}$/i',
            'key_id' => 'required|string|regex:/^[a-zA-Z0-9_-]{1,32}$/',
            'action' => 'sometimes|string|in:login|max:32',
        ];
    }
}
