<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

/**
 * View model for the default forums index (ADR 0021): the page heading
 * parts, per-overforum sections and the optional stats block.
 */
final class ForumIndexViewModel
{
    /**
     * @param  list<OverforumGroup>  $sections
     */
    public function __construct(
        public readonly string $siteName,
        public readonly bool $canManageForums,
        public readonly array $sections,
        public readonly ?ForumStatsViewModel $stats,
    ) {}
}
