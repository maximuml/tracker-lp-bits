<?php

declare(strict_types=1);

namespace App\DTOs\Forum;

use Illuminate\Http\Request;

/**
 * Immutable DTO for creating a forum.
 */
final readonly class StoreForumDto
{
    public function __construct(
        public string $name,
        public ?string $description,
        public int $forid,
        public int $minclassread,
        public int $minclasswrite,
        public int $minclasscreate,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'forid' => 'required|integer',
            'minclassread' => 'required|integer',
            'minclasswrite' => 'required|integer',
            'minclasscreate' => 'required|integer',
        ]);

        return new self(
            (string) $validated['name'],
            $validated['description'] !== null ? (string) $validated['description'] : null,
            (int) $validated['forid'],
            (int) $validated['minclassread'],
            (int) $validated['minclasswrite'],
            (int) $validated['minclasscreate'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'forid' => $this->forid,
            'minclassread' => $this->minclassread,
            'minclasswrite' => $this->minclasswrite,
            'minclasscreate' => $this->minclasscreate,
        ];
    }
}
