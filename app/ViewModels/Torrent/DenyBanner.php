<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * The deny-reason banner above the details table (approval workflow).
 */
final class DenyBanner
{
    public function __construct(
        public readonly SafeHtml $message,
    ) {}
}
