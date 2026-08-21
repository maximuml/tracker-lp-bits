<?php

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;

class CategoryActivity extends StatTable
{
    protected static ?int $sort = 201;

    protected function getHeader(): string
    {
        return __('admin.dashboard.category_activity');
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        return (new DashboardRepository())->categoryActivity();
    }
}
