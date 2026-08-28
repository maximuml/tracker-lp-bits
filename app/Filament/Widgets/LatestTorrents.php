<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Torrent;
use App\Support\Format;
use App\Support\TorrentAccess;
use App\Support\UserDisplay;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class LatestTorrents extends BaseWidget implements HasActions
{
    protected static ?int $sort = 2;

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('dashboard.latest_torrent.page_title');
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    /** @return Builder<Torrent> */
    protected function getTableQuery(): Builder
    {
        return Torrent::query()->orderBy('id', 'desc')->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('label.name'))
                ->formatStateUsing(fn ($record) => TorrentAccess::adminName($record, false, 30)),
            TextColumn::make('owner')
                ->label(__('label.torrent.owner'))
                ->formatStateUsing(fn ($state) => UserDisplay::adminUsername($state)),
            TextColumn::make('size')->formatStateUsing(fn ($state) => Format::size($state))->label(__('label.torrent.size')),
            TextColumn::make('added')->dateTime()->label(__('label.added')),
        ];
    }
}
