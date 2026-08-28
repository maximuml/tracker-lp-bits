<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;

class PeerAgents extends StatTable
{
    protected static ?int $sort = 202;

    protected function getHeader(): string
    {
        return __('admin.dashboard.peer_agents');
    }

    /** @return array<int|string, array<string, mixed>> */
    protected function getTableRows(): array
    {
        return (new DashboardRepository)->peerAgents();
    }
}
