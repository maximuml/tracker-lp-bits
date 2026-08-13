<?php

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;
use Illuminate\Contracts\View\View;
use Nexus\Database\NexusDB;

class UserClassStat extends StatTable
{
    protected static ?int $sort = 101;

    protected function getHeader(): string
    {
        return \App\Support\Locale::trans('dashboard.user_class.page_title', [], null);
    }

    protected function getTableRows(): array
    {
        $dashboardRep = new DashboardRepository();

        return $dashboardRep->statUserClass();
    }

}
