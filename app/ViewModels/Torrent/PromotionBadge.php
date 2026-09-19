<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * Typed promotion badge data for a torrent row — replaces the HTML
 * strings produced by `Promotion::append()`/`appendSub()`.
 *
 * `mode` is the user's `appendpromotion` rendering choice: 'word' prints
 * `[FREE]`-style text, 'icon' prints the `pro_*` sprite image. `timeout`
 * carries the formatted "time left" markup shown after the badge and
 * inside the domTT tooltip; `domttHtml` is the raw tooltip markup that
 * lands in the `data-domtt-promo` attribute (escaped by Blade).
 */
final class PromotionBadge
{
    public function __construct(
        public readonly string $mode,
        public readonly string $cssClass,
        public readonly string $iconClass,
        public readonly string $alt,
        public readonly string $text,
        public readonly ?SafeHtml $timeout,
        public readonly ?string $subColor,
        public readonly ?string $domttHtml,
    ) {}
}
