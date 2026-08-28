<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\TrackerStatistics\TopTorrents;
use App\Filament\Widgets\TrackerStatistics\TopUsers;
use App\Filament\Widgets\TrackerStatistics\TrackerOverview;
use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class TrackerStatistics extends Dashboard
{
    protected Width|string|null $maxContentWidth = 'full';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string $routePath = 'tracker-statistics';

    protected static string|\UnitEnum|null $navigationGroup = 'Tracker';

    protected static ?int $navigationSort = 14;

    public function getTitle(): string|Htmlable
    {
        return self::getNavigationLabel();
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.tracker_statistics');
    }

    /**
     * @return array<int, string>
     */
    public function getWidgets(): array
    {
        return [
            TrackerOverview::class,
            TopTorrents::class,
            TopUsers::class,
        ];
    }
}
