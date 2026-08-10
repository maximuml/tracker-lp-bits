<?php

declare(strict_types=1);

namespace App\Support\Config;

use App\Models\User;

final class AccountConfig extends Config
{
    public function deleteNoTransfer(int $default = 0): int
    {
        return $this->int('deletenotransfer', $default);
    }

    public function deleteNoTransferTwo(int $default = 0): int
    {
        return $this->int('deletenotransfertwo', $default);
    }

    public function deletePacked(int $default = 0): int
    {
        return $this->int('deletepacked', $default);
    }

    public function deletePeasant(int $default = 30): int
    {
        return $this->int('deletepeasant', $default);
    }

    public function deleteUnpacked(int $default = 0): int
    {
        return $this->int('deleteunpacked', $default);
    }

    public function destroyDisabled(int $default = 0): int
    {
        return $this->int('destroy_disabled', $default);
    }

    public function neverdelete(?int $default = null): int
    {
        $value = $this->data['neverdelete'] ?? $default ?? User::CLASS_VIP;
        return (int) $value;
    }

    public function neverdeletepacked(?int $default = null): int
    {
        $value = $this->data['neverdeletepacked'] ?? $default ?? User::CLASS_VIP;
        return (int) $value;
    }

    public function psdlfive(int $default = 0): int
    {
        return $this->int('psdlfive', $default);
    }

    public function psratiofive(float $default = 0.0): float
    {
        return $this->float('psratiofive', $default);
    }

    public function psdlfour(int $default = 0): int
    {
        return $this->int('psdlfour', $default);
    }

    public function psratiofour(float $default = 0.0): float
    {
        return $this->float('psratiofour', $default);
    }

    public function psdlthree(int $default = 0): int
    {
        return $this->int('psdlthree', $default);
    }

    public function psratiothree(float $default = 0.0): float
    {
        return $this->float('psratiothree', $default);
    }

    public function psdltwo(int $default = 0): int
    {
        return $this->int('psdltwo', $default);
    }

    public function psratiotwo(float $default = 0.0): float
    {
        return $this->float('psratiotwo', $default);
    }

    public function psdlone(int $default = 0): int
    {
        return $this->int('psdlone', $default);
    }

    public function psratioone(float $default = 0.0): float
    {
        return $this->float('psratioone', $default);
    }

    /**
     * @param array<int|string, mixed> $default
     * @return array<int|string, mixed>
     */
    public function getInvitesByPromotion(array $default = []): array
    {
        return $this->array('getInvitesByPromotion', $default);
    }

    public function classAlias(int|string $class): ?string
    {
        $key = "{$class}_alias";
        $value = $this->data[$key] ?? null;
        return $value !== null ? (string) $value : null;
    }

    public function classMinSeedPoints(int|string $class): ?int
    {
        $key = "{$class}_min_seed_points";
        $value = $this->data[$key] ?? null;
        return $value !== null ? (int) $value : null;
    }

    public function inviteByClass(int|string $class): ?int
    {
        $key = "{$class}_invite";
        $value = $this->data[$key] ?? null;
        return $value !== null ? (int) $value : null;
    }

    /**
     * @return array{string, int}  [$metricKey, $default]
     */
    private function promotionKeyAndDefault(int|string $class, string $metric): array
    {
        $map = [
            '2' => 'p',
            '3' => 'e',
            '4' => 'c',
            '5' => 'i',
            '6' => 'v',
            '7' => 'ex',
            '8' => 'u',
            '9' => 'nm',
        ];
        $prefix = $map[(string) $class] ?? null;
        if ($prefix === null) {
            return ['', 0];
        }
        $default = match ($metric) {
            'dl' => 0,
            'prratio' => 0,
            'time' => 0,
            default => 0,
        };
        return ["{$prefix}{$metric}", $default];
    }

    public function promotionDl(int|string $class, int $default = 0): int
    {
        [$key, $fallback] = $this->promotionKeyAndDefault($class, 'dl');
        return $this->int($key ?: 'never', $default ?: $fallback);
    }

    public function promotionRatio(int|string $class, float $default = 0.0): float
    {
        [$key, $fallback] = $this->promotionKeyAndDefault($class, 'prratio');
        return $this->float($key ?: 'never', $default ?: $fallback);
    }

    public function promotionTime(int|string $class, int $default = 0): int
    {
        [$key, $fallback] = $this->promotionKeyAndDefault($class, 'time');
        return $this->int($key ?: 'never', $default ?: $fallback);
    }

    public function demotionRatio(int|string $class, float $default = 0.0): float
    {
        $map = [
            '2' => 'puderatio',
            '3' => 'euderatio',
            '4' => 'cuderatio',
            '5' => 'iuderatio',
            '6' => 'vuderatio',
            '7' => 'exuderatio',
            '8' => 'uuderatio',
            '9' => 'nmderatio',
        ];
        $key = $map[(string) $class] ?? null;
        return $key !== null ? $this->float($key, $default) : $default;
    }

}
