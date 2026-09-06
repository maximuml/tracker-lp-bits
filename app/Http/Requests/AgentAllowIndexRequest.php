<?php

declare(strict_types=1);

namespace App\Http\Requests;

class AgentAllowIndexRequest extends IndexRequest
{
    /** @return array<int|string, mixed> */
    protected function filterRules(): array
    {
        return [
            'family' => 'nullable|string|max:100',
        ];
    }
}
