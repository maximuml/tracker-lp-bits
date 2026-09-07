<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\SitelogSecurityLevel;
use App\Models\SiteLog;

final class SiteLogRepository
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
}
