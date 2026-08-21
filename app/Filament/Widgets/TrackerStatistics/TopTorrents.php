<?php

namespace App\Filament\Widgets\TrackerStatistics;

use App\Models\Torrent;
use App\Support\Format;
use App\Support\TorrentAccess;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TopTorrents extends BaseWidget implements HasActions
{
    protected static ?int $sort = 20;

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('dashboard.tracker.top_torrents');
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    /**
     * @return Builder<Torrent>
     */
    protected function getTableQuery(): Builder
    {
        return Torrent::query()
            ->where('banned', 'no')
            ->orderByDesc('seeders')
            ->orderByDesc('leechers')
            ->limit(10);
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('label.name'))
                ->html()
                ->formatStateUsing(function (TextColumn $column, $state): HtmlString {
                    /** @var Torrent $record */
                    $record = $column->getRecord();

                    return TorrentAccess::adminName($record, false, 50);
                }),
            TextColumn::make('size')
                ->label(__('label.torrent.size'))
                ->formatStateUsing(fn ($state) => Format::size($state)),
            TextColumn::make('seeders')->label(__('label.torrent.seeders')),
            TextColumn::make('leechers')->label(__('label.torrent.leechers')),
            TextColumn::make('times_completed')->label(__('label.torrent.snatched')),
        ];
    }
}
