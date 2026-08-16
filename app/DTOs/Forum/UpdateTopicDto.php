<?php

declare(strict_types=1);

namespace App\DTOs\Forum;

use Illuminate\Http\Request;

/**
 * Immutable DTO for updating a forum topic.
 */
final readonly class UpdateTopicDto
{
    public function __construct(
        public ?string $subject,
        public ?bool $locked,
        public ?bool $sticky,
        public ?int $hlcolor,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'locked' => 'sometimes|boolean',
            'sticky' => 'sometimes|boolean',
            'hlcolor' => 'sometimes|integer',
        ]);

        return new self(
            isset($validated['subject']) ? (string) $validated['subject'] : null,
            isset($validated['locked']) ? (bool) $validated['locked'] : null,
            isset($validated['sticky']) ? (bool) $validated['sticky'] : null,
            isset($validated['hlcolor']) ? (int) $validated['hlcolor'] : null,
        );
    }
}
