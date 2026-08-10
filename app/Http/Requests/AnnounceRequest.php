<?php

namespace App\Http\Requests;

use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Http\FormRequest;

class AnnounceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::announceRules();
    }

    /** @return array<string, mixed> */
    public static function announceRules(): array
    {
        return [
            'passkey'    => [
                'required',
                'string',
                static function (string $attribute, mixed $value, callable $fail) {
                    if (! Passkey::tryFromString(is_string($value) ? $value : null)) {
                        $fail("The {$attribute} must be 32 characters.");
                    }
                },
            ],
            'info_hash'  => [
                'required',
                'string',
                static function (string $attribute, mixed $value, callable $fail) {
                    if (! InfoHash::tryFromBinary(is_string($value) ? $value : null)) {
                        $fail("The {$attribute} must be 20 bytes.");
                    }
                },
            ],
            'peer_id'    => [
                'required',
                'string',
                static function (string $attribute, mixed $value, callable $fail) {
                    if (! PeerId::tryFromBinary(is_string($value) ? $value : null)) {
                        $fail("The {$attribute} must be 20 bytes.");
                    }
                },
            ],
            'port'       => 'required|integer|between:1,65535',
            'uploaded'   => 'required|integer|min:0',
            'downloaded' => 'required|integer|min:0',
            'left'       => 'required|integer|min:0',
            'event'      => 'nullable|string|max:20',
            'numwant'    => 'nullable|string',
            'num_want'   => 'nullable|string',
            'ipv4'       => 'nullable|string',
            'ipv6'       => 'nullable|string',
            'compact'    => 'nullable|boolean',
        ];
    }
}
