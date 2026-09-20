<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * The "upped by" fragment on the details page. Anonymous torrents show
 * the localized placeholder; the real owner name is revealed only to
 * the owner or users with VIEW_ANONYMOUS.
 */
final class OwnerAttribution
{
    public function __construct(
        public readonly bool $anonymous,
        public readonly ?SafeHtml $username,
        public readonly bool $showUsername,
    ) {}
}
