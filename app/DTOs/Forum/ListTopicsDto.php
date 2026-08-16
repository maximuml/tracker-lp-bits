<?php

declare(strict_types=1);

namespace App\DTOs\Forum;

use Illuminate\Http\Request;

/**
 * Immutable DTO for listing forum topics.
 */
final readonly class ListTopicsDto
{
    public function __construct(
        public ?int $forumId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $forumId = $request->input('forum_id');

        return new self($forumId === null ? null : (int) $forumId);
    }
}
