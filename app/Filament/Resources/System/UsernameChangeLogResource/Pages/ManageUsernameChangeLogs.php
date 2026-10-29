<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\UsernameChangeLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\System\UsernameChangeLogResource;
use Filament\Pages\Actions;

class ManageUsernameChangeLogs extends PageListSingle
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = UsernameChangeLogResource::class;

    protected function userDisplayKey(): ?string
    {
        return 'uid';
    }

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
