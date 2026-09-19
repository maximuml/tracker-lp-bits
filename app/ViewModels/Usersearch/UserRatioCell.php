<?php

declare(strict_types=1);

namespace App\ViewModels\Usersearch;

/**
 * A ratio cell — plain number text plus an optional color span class
 * (`Ratio::colorClass`), or the `Inf.`/`---` sentinels.
 */
final class UserRatioCell
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $colorClass = null,
    ) {}
}
