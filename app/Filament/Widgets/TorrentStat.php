<?php

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;
use Illuminate\Contracts\View\View;
use Nexus\Database\NexusDB;

class TorrentStat extends StatTable
{
    protected static ?int $sort = 102;

    protected function getHeader(): string
    {
        return \App\Support\Locale::trans('dashboard.torrent.page_title', [], null);
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        $dashboardRep = new DashboardRepository();

        return $dashboardRep->statTorrents();
    }

}
