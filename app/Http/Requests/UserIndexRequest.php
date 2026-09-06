<?php

declare(strict_types=1);

namespace App\Http\Requests;

class UserIndexRequest extends IndexRequest
{
    /** @return array<int|string, mixed> */
    protected function filterRules(): array
    {
        return [
            'id' => 'nullable|integer',
            'username' => 'nullable|string|max:50',
            'email' => 'nullable|string|max:100',
            'class' => 'nullable|integer',
        ];
    }
}
