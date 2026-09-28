<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

use App\Support\Html\SafeHtml;

/**
 * Hover card of a today-active user in the stats strip.
 */
final readonly class IndexTodayUserCard
{
    public function __construct(
        public string $username,
        public SafeHtml $classLabel,
        public string $ratio,
        public string $uploaded,
        public string $downloaded,
        public string $lastSeen,
        public string $avatar,
    ) {}
}
