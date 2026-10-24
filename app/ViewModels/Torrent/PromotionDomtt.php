<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * Tooltip data for a promotion badge — the `data-domtt-promo` popover
 * content rendered inside the inline `<template>` by the
 * `x-torrent.promotion` component. `timeout` is the formatted
 * "time left" markup; all other fields are plain text escaped by Blade.
 */
final class PromotionDomtt
{
    public function __construct(
        public readonly string $cssClass,
        public readonly string $text,
        public readonly string $endIn,
        public readonly SafeHtml $timeout,
    ) {}
}
