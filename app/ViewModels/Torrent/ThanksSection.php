<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * The "Thanks" row: say-thanks button (disabled once given) plus the
 * list of thanking users. Element ids (`saythanks`, `thanksadded`,
 * `thanksbutton`, `curuser`, `nothanks`, `addcuruser`) are consumed by
 * common.js `saythanks()`.
 */
final class ThanksSection
{
    /**
     * @param  list<SafeHtml>  $thanksBy
     */
    public function __construct(
        public readonly int $torrentId,
        public readonly bool $hasThanked,
        public readonly string $buttonLabel,
        public readonly string $addedLabel,
        public readonly array $thanksBy,
        public readonly bool $noThanks,
        public readonly string $noThanksLabel,
        public readonly string $andMore,
        public readonly SafeHtml $currentUser,
    ) {}
}
