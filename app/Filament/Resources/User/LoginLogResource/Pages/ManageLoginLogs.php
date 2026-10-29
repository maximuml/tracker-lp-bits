<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\LoginLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\LoginLogResource;
use Filament\Pages\Actions;

class ManageLoginLogs extends PageListSingle
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = LoginLogResource::class;

    /** @return list<string> */
    protected function userDisplayKeys(): array
    {
        return ['uid'];
    }

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
