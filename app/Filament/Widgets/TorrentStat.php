<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;
use App\Support\Locale;

class TorrentStat extends StatTable
{
    protected static ?int $sort = 102;

    protected function getHeader(): string
    {
        return Locale::trans('dashboard.torrent.page_title', [], null);
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        $dashboardRep = app(DashboardRepository::class);

        return $dashboardRep->statTorrents();
    }
}
