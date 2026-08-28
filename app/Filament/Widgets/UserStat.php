<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;
use App\Support\Locale;

class UserStat extends StatTable
{
    protected static ?int $sort = 100;

    protected function getHeader(): string
    {
        return Locale::trans('dashboard.user.page_title', [], null);
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        $dashboardRep = app(DashboardRepository::class);

        return $dashboardRep->statUsers();
    }
}
