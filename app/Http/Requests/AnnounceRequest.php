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
        return [
            'passkey'    => 'required|string|size:32',
            'info_hash'  => 'required|string|size:20',
            'peer_id'    => 'required|string|size:20',
            'port'       => 'required|integer|between:1,65535',
            'uploaded'   => 'required|integer|min:0',
            'downloaded' => 'required|integer|min:0',
            'left'       => 'required|integer|min:0',
            'event'      => 'nullable|string|max:20',
            'numwant'    => 'nullable|integer|min:0|max:200',
            'num_want'   => 'nullable|integer|min:0|max:200',
            'ipv4'       => 'nullable|ipv4',
            'ipv6'       => 'nullable|ipv6',
            'compact'    => 'nullable|boolean',
        ];
    }
}
