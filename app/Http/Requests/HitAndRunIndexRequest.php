<?php

declare(strict_types=1);

namespace App\Http\Requests;

class HitAndRunIndexRequest extends IndexRequest
{
    /** @return array<int|string, mixed> */
    protected function filterRules(): array
    {
        return [
            'status' => 'nullable|string',
            'uid' => 'nullable|integer',
            'torrent_id' => 'nullable|integer',
            'username' => 'nullable|string|max:50',
        ];
    }
}
