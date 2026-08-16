<?php

declare(strict_types=1);

namespace App\DTOs\Forum;

use Illuminate\Http\Request;

/**
 * Immutable DTO for creating a forum post.
 */
final readonly class StorePostDto
{
    public function __construct(
        public string $body,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        return new self((string) $validated['body']);
    }
}
