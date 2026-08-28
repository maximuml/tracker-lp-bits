<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;
use App\Support\Locale;

class SystemInfo extends StatTable
{
    protected static ?int $sort = 1000;

    protected function getHeader(): string
    {
        return Locale::trans('dashboard.system_info.page_title', [], null);
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        $dashboardRep = new DashboardRepository;

        return $dashboardRep->getSystemInfo();
    }
}
