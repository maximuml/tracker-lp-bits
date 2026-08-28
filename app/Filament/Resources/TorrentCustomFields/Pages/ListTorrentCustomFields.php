<?php

declare(strict_types=1);

namespace App\Filament\Resources\TorrentCustomFields\Pages;

use App\Filament\PageList;
use App\Filament\Resources\TorrentCustomFields\TorrentCustomFieldResource;
use Filament\Actions\CreateAction;

class ListTorrentCustomFields extends PageList
{
    protected static string $resource = TorrentCustomFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
