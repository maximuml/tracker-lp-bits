<?php

declare(strict_types=1);

namespace App\Http\Requests;

class AgentDenyIndexRequest extends IndexRequest
{
    /** @return array<int|string, mixed> */
    protected function filterRules(): array
    {
        return [
            'family_id' => 'nullable|integer',
        ];
    }
}
