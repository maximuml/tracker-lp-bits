<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface MysqlStatsRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function status(): array;

    /**
     * @return list{string, string}
     */
    public function formatByteDown(float $value, int $limes = 6, int $comma = 0): array;

    public function timespanFormat(int $seconds): string;

    public function localisedDate(int $timestamp = -1, string $format = ''): string;
}
