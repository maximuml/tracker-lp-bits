<?php

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;

class UploaderActivity extends StatTable
{
    protected static ?int $sort = 200;

    protected function getHeader(): string
    {
        return __('admin.dashboard.uploader_activity');
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        return (new DashboardRepository)->uploaderActivity();
    }
}
