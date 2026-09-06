<?php

declare(strict_types=1);

namespace App\Http\Requests;

class SettingIndexRequest extends IndexRequest
{
    /** @return array<int|string, mixed> */
    protected function filterRules(): array
    {
        return [
            'prefix' => 'nullable|string|max:100',
        ];
    }
}
