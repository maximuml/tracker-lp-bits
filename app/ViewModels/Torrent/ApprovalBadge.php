<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * Approval-status icon for a torrent row. `icon` is the static SVG from
 * `Torrent::$approvalStatus` — a class constant, not user input, so it
 * arrives as SafeHtml; `title` is the localized status label.
 */
final class ApprovalBadge
{
    public function __construct(
        public readonly string $title,
        public readonly SafeHtml $icon,
    ) {}
}
