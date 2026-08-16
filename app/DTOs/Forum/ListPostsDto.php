<?php

declare(strict_types=1);

namespace App\DTOs\Forum;

use Illuminate\Http\Request;

/**
 * Immutable DTO for paginated forum posts within a topic.
 */
final readonly class ListPostsDto
{
    public function __construct(
        public int $perPage,
        public int $page,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            max(1, min(100, (int) $request->input('per_page', 20))),
            max(1, (int) $request->input('page', 1)),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
