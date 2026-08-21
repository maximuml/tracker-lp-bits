<?php

namespace App\Filament\Resources\Oauth\ProviderResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\ProviderResource;
use Filament\Actions\CreateAction;

class ManageProviders extends PageListSingle
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
