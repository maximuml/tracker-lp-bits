<?php

declare(strict_types=1);

namespace App\DTOs\Message;

use Illuminate\Http\Request;

/**
 * Immutable DTO for updating a message (mark read/unread or move mailbox).
 */
final readonly class UpdateMessageDto
{
    public function __construct(
        public ?string $unread,
        public ?int $location,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'unread' => 'sometimes|in:yes,no',
            'location' => 'sometimes|integer',
        ]);

        return new self(
            isset($validated['unread']) ? (string) $validated['unread'] : null,
            isset($validated['location']) ? (int) $validated['location'] : null,
        );
    }
}
