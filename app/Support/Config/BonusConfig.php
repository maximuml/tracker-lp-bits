<?php

declare(strict_types=1);

namespace App\Support\Config;

final class BonusConfig extends Config
{
    public function officialTag(string $default = ''): string
    {
        return $this->string('official_tag', $default);
    }

    public function officialAddition(float $default = 0.0): float
    {
        return $this->float('official_addition', $default);
    }

    public function haremAddition(float $default = 0.0): float
    {
        return $this->float('harem_addition', $default);
    }

    public function donorTimes(float $default = 0.0): float
    {
        return $this->float('donortimes', $default);
    }

    public function minSize(float $default = 0.0): float
    {
        return $this->float('min_size', $default);
    }

    public function addComment(float $default = 0.0): float
    {
        return $this->float('addcomment', $default);
    }

    public function receiveThanks(float $default = 0.0): float
    {
        return $this->float('receivethanks', $default);
    }

    public function sayThanks(float $default = 0.0): float
    {
        return $this->float('saythanks', $default);
    }

    public function zeroBonusFactor(float $default = 0.0): float
    {
        return $this->float('zero_bonus_factor', $default);
    }

    public function zeroBonusTag(string $default = ''): string
    {
        return $this->string('zero_bonus_tag', $default);
    }

    public function oneTmpInvite(string $default = ''): string
    {
        return $this->string('one_tmp_invite', $default);
    }

    public function changeUsernameCard(string $default = ''): string
    {
        return $this->string('change_username_card', $default);
    }

    public function cancelHr(string $default = ''): string
    {
        return $this->string('cancel_hr', $default);
    }

    public function attendanceCard(string $default = ''): string
    {
        return $this->string('attendance_card', $default);
    }

    public function rainbowId(string $default = ''): string
    {
        return $this->string('rainbow_id', $default);
    }

}
