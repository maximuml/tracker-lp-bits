<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * One file row of the viewfilelist AJAX fragment — filename plus its
 * FileBadge extension badge and formatted size.
 */
final class ViewFileListRow
{
    /**
     * @param  array{cat: string, label: string}  $badge
     */
    public function __construct(
        public readonly array $badge,
        public readonly string $filename,
        public readonly string $size,
    ) {}
}
