<?php

declare(strict_types=1);

namespace App\Filament\Resources\Torrent\TorrentResource\Pages;

use App\Filament\Resources\Torrent\TorrentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTorrent extends CreateRecord
{
    protected static string $resource = TorrentResource::class;
}
