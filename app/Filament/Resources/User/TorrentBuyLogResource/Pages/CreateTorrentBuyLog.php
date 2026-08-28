<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\TorrentBuyLogResource\Pages;

use App\Filament\Resources\User\TorrentBuyLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTorrentBuyLog extends CreateRecord
{
    protected static string $resource = TorrentBuyLogResource::class;
}
