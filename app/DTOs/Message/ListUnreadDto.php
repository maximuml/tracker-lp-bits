<?php

declare(strict_types=1);

namespace App\DTOs\Message;

use Illuminate\Http\Request;

/**
 * Immutable DTO for the unread-message list endpoint.
 */
final readonly class ListUnreadDto
{
    public function __construct(
        public int $perPage,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            max(1, min(100, (int) $request->input('per_page', 20))),
        );
    }
}
