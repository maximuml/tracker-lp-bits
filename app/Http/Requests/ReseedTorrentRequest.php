<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/torrents/reseed — the REST rename of POST /takereseed
 * (ask seeders to re-seed a dead torrent). `reseedid`/`id` travel in
 * the query string for legacy callers; domain checks stay in the
 * controller so the error pages remain byte-identical.
 */
class ReseedTorrentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reseedid' => 'nullable|integer',
            'id' => 'nullable|integer',
        ];
    }
}
