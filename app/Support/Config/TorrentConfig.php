<?php

declare(strict_types=1);

namespace App\Support\Config;

use App\Enums\TorrentPromotion;

final class TorrentConfig extends Config
{
    public function approvalStatusIconEnabled(bool $default = false): bool
    {
        return $this->bool('approval_status_icon_enabled', $default);
    }

    public function approvalStatusNoneVisible(bool $default = false): bool
    {
        return $this->bool('approval_status_none_visible', $default);
    }

    public function delDeadTorrent(int $default = 0): int
    {
        return $this->int('deldeadtorrent', $default);
    }

    public function expireHalfleech(int $default = 0): int
    {
        return $this->int('expirehalfleech', $default);
    }

    public function halfleechbecome(int $default = TorrentPromotion::NORMAL->value): int
    {
        return $this->int('halfleechbecome', $default);
    }

    public function expireFree(int $default = 0): int
    {
        return $this->int('expirefree', $default);
    }

    public function freebecome(int $default = TorrentPromotion::NORMAL->value): int
    {
        return $this->int('freebecome', $default);
    }

    public function expireTwoup(int $default = 0): int
    {
        return $this->int('expiretwoup', $default);
    }

    public function twoupbecome(int $default = TorrentPromotion::NORMAL->value): int
    {
        return $this->int('twoupbecome', $default);
    }

    public function expireTwoupfree(int $default = 0): int
    {
        return $this->int('expiretwoupfree', $default);
    }

    public function twoupfreebecome(int $default = TorrentPromotion::NORMAL->value): int
    {
        return $this->int('twoupfreebecome', $default);
    }

    public function expireTwouphalfleech(int $default = 0): int
    {
        return $this->int('expiretwouphalfleech', $default);
    }

    public function twouphalfleechbecome(int $default = TorrentPromotion::NORMAL->value): int
    {
        return $this->int('twouphalfleechbecome', $default);
    }

    public function expireThirtypercentleech(int $default = 0): int
    {
        return $this->int('expirethirtypercentleech', $default);
    }

    public function thirtypercentleechbecome(int $default = TorrentPromotion::NORMAL->value): int
    {
        return $this->int('thirtypercentleechbecome', $default);
    }

    public function expireNormal(int $default = 0): int
    {
        return $this->int('expirenormal', $default);
    }

    public function normalbecome(int $default = TorrentPromotion::NORMAL->value): int
    {
        return $this->int('normalbecome', $default);
    }

    public function paidTorrentEnabled(bool $default = false): bool
    {
        return $this->bool('paid_torrent_enabled', $default);
    }

    public function uploaderdouble(float $default = 1.0): float
    {
        return $this->float('uploaderdouble', $default);
    }

    public function stickyFirstLevelBackgroundColor(?string $default = null): ?string
    {
        $value = $this->data['sticky_first_level_background_color'] ?? $default;

        return $value !== null ? (string) $value : null;
    }

    public function stickySecondLevelBackgroundColor(?string $default = null): ?string
    {
        $value = $this->data['sticky_second_level_background_color'] ?? $default;

        return $value !== null ? (string) $value : null;
    }

    public function downloadSupportPasskey(bool $default = false): bool
    {
        return $this->bool('download_support_passkey', $default);
    }

    public function taxFactor(float $default = 0.0): float
    {
        return $this->float('tax_factor', $default);
    }

    public function maxPrice(int $default = 0): int
    {
        return $this->int('max_price', $default);
    }

    public function largeSize(int $default = 0): int
    {
        return $this->int('largesize', $default);
    }

    public function largeSpState(int $default = 0): int
    {
        return $this->int('largepro', $default);
    }

    public function randomFreeProbability(int $default = 0): int
    {
        return $this->int('randomfree', $default);
    }

    public function randomTwoTimesUpProbability(int $default = 0): int
    {
        return $this->int('randomtwoup', $default);
    }

    public function randomFreeTwoTimesUpProbability(int $default = 0): int
    {
        return $this->int('randomtwoupfree', $default);
    }

    public function randomHalfDownProbability(int $default = 0): int
    {
        return $this->int('randomhalfleech', $default);
    }

    public function randomHalfDownTwoTimesUpProbability(int $default = 0): int
    {
        return $this->int('randomtwouphalfdown', $default);
    }

    public function randomOneThirdDownProbability(int $default = 0): int
    {
        return $this->int('randomthirtypercentdown', $default);
    }
}
