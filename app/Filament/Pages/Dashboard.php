<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountInfo;
use App\Filament\Widgets\LatestTorrents;
use App\Filament\Widgets\LatestUsers;
use App\Filament\Widgets\SystemInfo;
use App\Filament\Widgets\TorrentTrend;
use App\Filament\Widgets\UserTrend;
use Filament\Support\Enums\Width;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected Width|string|null $maxContentWidth = 'full';

    public function getWidgets(): array
    {
        return [
            AccountInfo::class,
            LatestUsers::class,
            LatestTorrents::class,
            UserTrend::class,
            TorrentTrend::class,
            SystemInfo::class,
        ];
    }
}
