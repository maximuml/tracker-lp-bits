<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/torrents/delete — the REST rename of POST /delete
 * (owner/staff torrent delete; a blank id renders the confirm page,
 * a query-string id still hits the 'Party is over' abort).
 */
class DeleteTorrentRequest extends FormRequest
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
