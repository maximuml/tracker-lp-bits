<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SiteLog;

final class SiteLogRepository
{
    public static function create(string $text, string $security = 'normal', ?int $userId = null): void
    {
        SiteLog::query()->insert([
            'added' => now(),
            'txt' => $text,
            'security_level' => $security,
            'uid' => $userId ?? 0,
        ]);
    }
}
