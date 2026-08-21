<?php

namespace App\Filament\Resources\Oauth\ClientResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\ClientResource;
use Filament\Actions\CreateAction;

class ManageClients extends PageListSingle
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
