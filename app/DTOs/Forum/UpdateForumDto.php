<?php

declare(strict_types=1);

namespace App\DTOs\Forum;

use Illuminate\Http\Request;

/**
 * Immutable DTO for updating a forum.
 */
final readonly class UpdateForumDto
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?int $forid,
        public ?int $minclassread,
        public ?int $minclasswrite,
        public ?int $minclasscreate,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'forid' => 'sometimes|integer',
            'minclassread' => 'sometimes|integer',
            'minclasswrite' => 'sometimes|integer',
            'minclasscreate' => 'sometimes|integer',
        ]);

        return new self(
            isset($validated['name']) ? (string) $validated['name'] : null,
            array_key_exists('description', $validated) ? ($validated['description'] !== null ? (string) $validated['description'] : null) : null,
            isset($validated['forid']) ? (int) $validated['forid'] : null,
            isset($validated['minclassread']) ? (int) $validated['minclassread'] : null,
            isset($validated['minclasswrite']) ? (int) $validated['minclasswrite'] : null,
            isset($validated['minclasscreate']) ? (int) $validated['minclasscreate'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'forid' => $this->forid,
            'minclassread' => $this->minclassread,
            'minclasswrite' => $this->minclasswrite,
            'minclasscreate' => $this->minclasscreate,
        ], fn ($value) => $value !== null);
    }
}
