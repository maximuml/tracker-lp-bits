<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\TorrentBuyLogResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\TorrentBuyLogResource;
use App\Models\TorrentBuyLog;
use Filament\Pages\Actions;
use Illuminate\Database\Eloquent\Builder;

class ListTorrentBuyLogs extends PageList
{
    protected static string $resource = TorrentBuyLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }

    /** @return Builder<TorrentBuyLog> */
    protected function getTableQuery(): Builder
    {
        return TorrentBuyLog::query()->with(['user', 'torrent']);
    }
}
