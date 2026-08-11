<?php

namespace App\Filament\Widgets\TrackerStatistics;

use App\Models\User;
use App\Support\Ratio;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class TopUsers extends BaseWidget implements HasActions
{
    protected static ?int $sort = 30;

    protected function getTableHeading(): string | Htmlable | null
    {
        return __('dashboard.tracker.top_users');
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    /**
     * @return Builder<User>
     */
    protected function getTableQuery(): Builder
    {
        return User::query()
            ->where('enabled', User::ENABLED_YES)
            ->orderByDesc('uploaded')
            ->limit(10);
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label(__('label.user.username'))
                ->formatStateUsing(fn (int $state) => username_for_admin($state))
            ,
            TextColumn::make('uploaded')
                ->label(__('label.uploaded'))
                ->formatStateUsing(fn ($state) => \App\Support\Format::size($state))
            ,
            TextColumn::make('downloaded')
                ->label(__('label.downloaded'))
                ->formatStateUsing(fn ($state) => \App\Support\Format::size($state))
            ,
            TextColumn::make('share_ratio')
                ->label(__('label.ratio'))
                ->state(fn (User $record): string => (string) Ratio::share((float) $record->uploaded, (float) $record->downloaded))
            ,
        ];
    }
}
