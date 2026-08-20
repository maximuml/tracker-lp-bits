<?php

namespace App\Filament\Resources\Oauth\AuthCodeResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\AuthCodeResource;
use Filament\Pages\Actions;

class ManageAuthCodes extends PageListSingle
{
    protected static string $resource = AuthCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
