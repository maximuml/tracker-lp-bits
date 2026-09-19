<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * The "Magic value of awards" row.
 *
 * `disabledValue` — when set, a single disabled button is rendered
 * instead of the clickable options list ('more points needed' for
 * low-bonus users, the already-given text for past givers). `options`
 * carries the clickable bonus values for the `ul.magic` list.
 * The giver list is split at six entries: `hiddenGivers` sit behind the
 * `#magic_show_all` toggle.
 */
final class MagicSection
{
    /**
     * @param  list<int>  $options
     * @param  list<SafeHtml>  $visibleGivers
     * @param  list<SafeHtml>  $hiddenGivers
     */
    public function __construct(
        public readonly int $torrentId,
        public readonly array $options,
        public readonly ?string $disabledValue,
        public readonly string $givenLabel,
        public readonly int $sumValue,
        public readonly int $countUserNumber,
        public readonly array $visibleGivers,
        public readonly array $hiddenGivers,
        public readonly SafeHtml $currentUser,
        public readonly string $newestRecordText,
        public readonly string $sumGivePre,
        public readonly string $sumGivePost,
        public readonly string $showAllText,
        public readonly string $haveGotBonusPre,
        public readonly string $haveGotBonusPost,
    ) {}

    public function hasGivers(): bool
    {
        return $this->visibleGivers !== [] || $this->hiddenGivers !== [];
    }
}
