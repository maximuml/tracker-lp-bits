<?php

namespace App\Filament\Resources\Torrent\TorrentDenyReasonResource\Pages;

use App\Filament\Resources\Torrent\TorrentDenyReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageTorrentDenyReasons extends ManageRecords
{
    protected Width|string|null $maxContentWidth = 'full';

    protected static string $resource = TorrentDenyReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
