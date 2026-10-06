<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/torrents/fast-delete — the REST rename of POST /fastdelete
 * (staff one-click torrent delete with a `sure` confirm flag).
 */
class FastDeleteTorrentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'sure' => 'nullable|string',
        ];
    }
}
