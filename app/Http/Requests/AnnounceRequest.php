<?php

namespace App\Http\Requests;

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
        $binarySize = static function (string $attribute, mixed $value, callable $fail, int $size) {
            if (!is_string($value) || strlen($value) !== $size) {
                $fail("The {$attribute} must be {$size} bytes.");
            }
        };

        return [
            'passkey'    => 'required|string|size:32',
            'info_hash'  => [
                'required',
                'string',
                static function (string $attribute, mixed $value, callable $fail) use ($binarySize) {
                    $binarySize($attribute, $value, $fail, 20);
                },
            ],
            'peer_id'    => [
                'required',
                'string',
                static function (string $attribute, mixed $value, callable $fail) use ($binarySize) {
                    $binarySize($attribute, $value, $fail, 20);
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
