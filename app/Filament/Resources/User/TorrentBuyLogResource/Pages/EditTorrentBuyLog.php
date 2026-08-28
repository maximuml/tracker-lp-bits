<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\TorrentBuyLogResource\Pages;

use App\Filament\Resources\User\TorrentBuyLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTorrentBuyLog extends EditRecord
{
    protected static string $resource = TorrentBuyLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
