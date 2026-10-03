<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;

class DonorSummary extends StatTable
{
    protected static ?int $sort = 203;

    private DashboardRepository $dashboardRepository;

    public function boot(DashboardRepository $dashboardRepository): void
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    protected function getHeader(): string
    {
        return __('admin.dashboard.donor_summary');
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        return $this->dashboardRepository->donorSummary();
    }
}
