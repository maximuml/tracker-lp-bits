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

    public function oneTmpInvite(float $default = 0.0): float
    {
        return $this->float('one_tmp_invite', $default);
    }

    public function changeUsernameCard(float $default = 0.0): float
    {
        return $this->float('change_username_card', $default);
    }

    public function cancelHr(float $default = 0.0): float
    {
        return $this->float('cancel_hr', $default);
    }

    public function attendanceCard(float $default = 0.0): float
    {
        return $this->float('attendance_card', $default);
    }

    public function rainbowId(float $default = 0.0): float
    {
        return $this->float('rainbow_id', $default);
    }

    public function hundredGbUpload(float $default = 0.0): float
    {
        return $this->float('hundredgbupload', $default);
    }

    public function tenGbDownload(float $default = 0.0): float
    {
        return $this->float('tengbdownload', $default);
    }

    public function hundredGbDownload(float $default = 0.0): float
    {
        return $this->float('hundredgbdownload', $default);
    }

    public function attendanceInitial(float $default = 0.0): float
    {
        return $this->float('attendance_initial', $default);
    }

    public function attendanceStep(float $default = 0.0): float
    {
        return $this->float('attendance_step', $default);
    }

    public function attendanceMax(float $default = 0.0): float
    {
        return $this->float('attendance_max', $default);
    }

    /**
     * @param  array<int|float, float>  $default
     * @return array<int|float, float>
     */
    public function attendanceContinuous(array $default = []): array
    {
        /** @var array<int|float, float> $value */
        $value = $this->array('attendance_continuous', $default);

        return array_map('floatval', $value);
    }

    public function selfEnable(int $default = 0): int
    {
        return $this->int('self_enable', $default);
    }

    public function uploadTorrent(int $default = 0): int
    {
        return $this->int('uploadtorrent', $default);
    }
}
