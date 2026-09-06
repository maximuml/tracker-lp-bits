<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Generic index request for list endpoints without resource-specific filters.
 * Only validates common sort/pagination params.
 */
class GenericIndexRequest extends IndexRequest
{
    /** @return array<int|string, mixed> */
    protected function filterRules(): array
    {
        return [];
    }
}
