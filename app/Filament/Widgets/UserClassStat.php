<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;
use App\Support\Locale;

class UserClassStat extends StatTable
{
    protected static ?int $sort = 101;

    protected function getHeader(): string
    {
        return Locale::trans('dashboard.user_class.page_title', [], null);
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        $dashboardRep = new DashboardRepository;

        return $dashboardRep->statUserClass();
    }
}
