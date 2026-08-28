<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserModifyLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\User\UserModifyLogResource;
use Filament\Actions;
use Filament\Actions\Contracts\HasActions;

class ManageUserModifyLogs extends PageListSingle implements HasActions
{
    protected static string $resource = UserModifyLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
