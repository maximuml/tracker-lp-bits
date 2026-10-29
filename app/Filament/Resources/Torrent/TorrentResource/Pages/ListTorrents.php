<?php

declare(strict_types=1);

namespace App\Filament\Resources\Torrent\TorrentResource\Pages;

use App\Filament\PageList;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\Torrent\TorrentResource;
use Filament\Pages\Actions;

class ListTorrents extends PageList
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = TorrentResource::class;

    /** @return list<string> */
    protected function userDisplayKeys(): array
    {
        return ['owner'];
    }

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
