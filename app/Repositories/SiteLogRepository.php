<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\SiteLogRepositoryInterface;
use App\Enums\SitelogSecurityLevel;
use App\Models\SiteLog;

final class SiteLogRepository implements SiteLogRepositoryInterface
{
    public function create(string $text, string $security = 'normal', ?int $userId = null): void
    {
        SiteLog::query()->insert([
            'added' => now(),
            'txt' => $text,
            'security_level' => SitelogSecurityLevel::fromStringSafe($security)->value,
            'uid' => $userId ?? 0,
        ]);
    }

    public function deleteBefore(string $before): int
    {
        return SiteLog::query()->where('added', '<', $before)->delete();
    }
}
