<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BonusCalculationRepository;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\UserDisplay;
use App\ViewModels\Bonus\BonusTableViewFactory;

/**
 * User-level seed-bonus orchestration drained out of
 * `calculate_seed_bonus()` / `calculate_harem_addition()` /
 * `build_bonus_table()`: fetches rows and settings, runs the pure
 * {@see BonusCalculator}, and assembles the per-user breakdown table.
 */
final class SeedBonusService
{
    public function __construct(
        private readonly BonusCalculationRepository $bonusCalculationRepository,
        private readonly BonusCalculator $calculator,
        private readonly BonusTableViewFactory $bonusTableViewFactory,
    ) {}

    /**
     * Fetch data and compute a user's per-hour seed bonus.
     *
     * Mirrors the non-deterministic half of `calculate_seed_bonus()`.
     *
     * @param  array<int>|null  $torrentIdArr
     * @return array<string, mixed>
     */
    public function calculateForUser(int $uid, ?array $torrentIdArr = null): array
    {
        $bonusConfig = SiteConfig::current()->bonus;
        $settingBonus = [
            'donortimes' => $bonusConfig->donorTimes(),
            'perseeding' => $bonusConfig->perSeeding(),
            'maxseeding' => $bonusConfig->maxSeeding(),
            'tzero' => $bonusConfig->tZero(),
            'nzero' => $bonusConfig->nZero(),
            'bzero' => $bonusConfig->bZero(),
            'l' => $bonusConfig->l(),
            'min_size' => $bonusConfig->minSize(),
        ];
        $minSize = $settingBonus['min_size'];
        $logPrefix = "[CALCULATE_SEED_BONUS], uid: $uid, torrentIdArr: ".json_encode($torrentIdArr);

        $torrentData = $this->bonusCalculationRepository->getTorrentRowsForBonusCalculation($uid, $torrentIdArr, $minSize);
        $sql = $torrentData['sql'];
        $torrentResult = $torrentData['torrentResult'];

        $tagGrouped = [];
        if (! empty($torrentResult)) {
            $torrentIdArrReal = array_column($torrentResult, 'id');
            $tagGrouped = $this->bonusCalculationRepository->getTagGrouped($torrentIdArrReal);
        }

        $officialTag = SiteConfig::current()->bonus->officialTag();
        $officialAdditionalFactor = SiteConfig::current()->bonus->officialAddition();
        $zeroBonusTag = SiteConfig::current()->bonus->zeroBonusTag();
        $zeroBonusFactor = SiteConfig::current()->bonus->zeroBonusFactor();

        Logger::writeWithContext("$logPrefix, sql: $sql, count: ".count($torrentResult).", officialTag: $officialTag, officialAdditionalFactor: $officialAdditionalFactor, zeroBonusTag: $zeroBonusTag, zeroBonusFactor: $zeroBonusFactor");

        $result = $this->calculator->aggregateSeedBonus(
            $torrentResult,
            $settingBonus,
            $tagGrouped,
            $officialTag,
            $zeroBonusTag,
            $zeroBonusFactor,
            $officialAdditionalFactor,
            function ($torrent, $weeks_alive, $gb_size_raw, $gb_size, $temp, $officialAIncrease) use ($logPrefix) {
                Logger::writeWithContext(sprintf(
                    "$logPrefix, torrent: %s, peer ID: %s, weeks: %s, size_raw: %s GB, size: %s GB, increase A: %s, increase official A: %s",
                    $torrent['id'], $torrent['peerID'] ?? '', $weeks_alive, $gb_size_raw, $gb_size, $temp, $officialAIncrease
                ), 'debug');
            },
        );

        Logger::writeWithContext("$logPrefix, result: ".json_encode($result));

        return $result;
    }

    /**
     * Compute the harem seed-bonus addition for a user.
     *
     * Mirrors `calculate_harem_addition()`.
     */
    public function haremAddition(int $uid): float|int|string
    {
        return $this->bonusCalculationRepository->getHaremAddition($uid);
    }

    /**
     * Build the seed-bonus breakdown table for a user, resolving all
     * the legacy helper dependencies (`calculate_seed_bonus`,
     * `calculate_harem_addition`, `is_donor`, settings) internally.
     *
     * Backs the `build_bonus_table()` helper.
     *
     * @param  array<int|string, mixed>  $user
     * @param  array<string, mixed>  $bonusResult
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function buildBonusTableForUser(array $user, array $bonusResult = [], array $options = []): array
    {
        if (empty($bonusResult)) {
            $bonusResult = $this->calculateForUser((int) ($user['id'] ?? 0));
        }

        $officialTag = (string) SiteConfig::current()->bonus->officialTag();
        $officialAdditionalFactor = (float) SiteConfig::current()->bonus->officialAddition(0);
        $haremFactor = (float) SiteConfig::current()->bonus->haremAddition();
        $haremAddition = (float) $this->haremAddition((int) ($user['id'] ?? 0));
        $isDonor = UserDisplay::isDonor($user);
        $donortimesBonus = (float) SiteConfig::current()->bonus->donorTimes();

        return $this->bonusTableViewFactory->build(
            $bonusResult,
            $isDonor,
            $donortimesBonus,
            $officialTag,
            $officialAdditionalFactor,
            $haremFactor,
            $haremAddition,
            $options,
        );
    }
}
