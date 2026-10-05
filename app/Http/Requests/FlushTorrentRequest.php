<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/torrents/flush — the REST rename of POST /takeflush
 * (purge a user's dead peer rows). `id` arrives in the query string
 * for the legacy callers, so it stays permissive here and the
 * controller keeps its legacy 'Invalid ID.' abort.
 */
class FlushTorrentRequest extends FormRequest
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
        ];
    }
}
