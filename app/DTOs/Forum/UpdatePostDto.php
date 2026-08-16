<?php

declare(strict_types=1);

namespace App\DTOs\Forum;

use Illuminate\Http\Request;

/**
 * Immutable DTO for updating a forum post.
 */
final readonly class UpdatePostDto
{
    public function __construct(
        public string $body,
        public ?string $subject,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'subject' => 'sometimes|nullable|string|max:255',
        ]);

        return new self(
            (string) $validated['body'],
            isset($validated['subject']) ? (string) $validated['subject'] : null,
        );
    }
}
