<?php

namespace App\Filament\Resources\Security\BanResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\PageList;
use App\Filament\Resources\Security\BanResource;
use Filament\Resources\Pages\ListRecords;

class ListBans extends PageList
{
    protected static string $resource = BanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
