<?php

declare(strict_types=1);

namespace App\Filament\Widgets\TrackerStatistics;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;

class TrackerOverview extends StatTable
{
    protected static ?int $sort = 10;

    protected function getHeader(): string
    {
        return __('dashboard.tracker.overview');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getTableRows(): array
    {
        $dashboardRep = app(DashboardRepository::class);

        return $dashboardRep->statTracker();
    }
}
