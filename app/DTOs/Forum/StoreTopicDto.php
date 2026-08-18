<?php

declare(strict_types=1);

namespace App\DTOs\Forum;

use Illuminate\Http\Request;

/**
 * Immutable DTO for creating a forum topic with its first post.
 */
final readonly class StoreTopicDto
{
    public function __construct(
        public int $forumId,
        public string $subject,
        public string $body,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'forumid' => 'required|integer|exists:forums,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        return new self(
            (int) $validated['forumid'],
            (string) $validated['subject'],
            (string) $validated['body'],
        );
    }
}
