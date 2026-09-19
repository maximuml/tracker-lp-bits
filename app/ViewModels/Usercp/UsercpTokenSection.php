<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

/**
 * API-token management block of the usercp home section.
 *
 * `permissions`/`items` carry plain data; the view renders the token
 * table (`x-data-table`), the hidden create-form template and the
 * permission checkboxes. The service still appends the wiring JS via
 * `AssetAppender` — it reads the form markup from the hidden template.
 */
final readonly class UsercpTokenSection
{
    /**
     * @param  list<array{value: string, label: string}>  $permissions
     * @param  list<array{id: int, name: string, abilities: string, createdAt: string}>  $items
     */
    public function __construct(
        public string $label,
        public string $columnName,
        public string $columnPermission,
        public string $columnCreatedAt,
        public string $actionLabel,
        public string $actionCreate,
        public array $permissions,
        public array $items,
        public string $deleteLabel,
        public string $confirmRemoveLabel,
    ) {}
}
