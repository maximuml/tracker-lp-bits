<?php

declare(strict_types=1);

namespace App\Support\Config;

final class SeedBoxConfig extends Config
{
    public function enabled(bool $default = false): bool
    {
        return $this->bool('enabled', $default);
    }

    public function notSeedBoxMaxSpeed(float $default = 0.0): float
    {
        return $this->float('not_seed_box_max_speed', $default);
    }

    public function noPromotion(bool $default = false): bool
    {
        return $this->bool('no_promotion', $default);
    }

    public function maxUploaded(int $default = 0): int
    {
        return $this->int('max_uploaded', $default);
    }

    public function maxUploadedDuration(int $default = 0): int
    {
        return $this->int('max_uploaded_duration', $default);
    }

}
