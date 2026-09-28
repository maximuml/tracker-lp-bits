<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * One news entry rendered on the index page.
 */
final readonly class IndexNewsItem
{
    public function __construct(
        public int $id,
        public string $added,
        public string $title,
        public string $body,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            added: (string) ($row['added'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            body: (string) ($row['body'] ?? ''),
        );
    }
}
