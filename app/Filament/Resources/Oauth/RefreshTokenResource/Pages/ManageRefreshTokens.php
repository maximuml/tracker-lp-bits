<?php

namespace App\Filament\Resources\Oauth\RefreshTokenResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\RefreshTokenResource;
use Filament\Pages\Actions;

class ManageRefreshTokens extends PageListSingle
{
    protected static string $resource = RefreshTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
