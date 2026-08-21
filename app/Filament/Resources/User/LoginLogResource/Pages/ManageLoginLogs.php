<?php

namespace App\Filament\Resources\User\LoginLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\User\LoginLogResource;
use Filament\Pages\Actions;

class ManageLoginLogs extends PageListSingle
{
    protected static string $resource = LoginLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
