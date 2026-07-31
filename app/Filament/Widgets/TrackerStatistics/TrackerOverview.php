<?php

namespace App\Filament\Widgets\TrackerStatistics;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;
use Illuminate\Contracts\View\View;

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
        $dashboardRep = new DashboardRepository();

        return $dashboardRep->statTracker();
    }
}
