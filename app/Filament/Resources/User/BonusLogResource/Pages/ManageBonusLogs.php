<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\BonusLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\BonusLogResource;
use Filament\Pages\Actions;

class ManageBonusLogs extends PageListSingle
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = BonusLogResource::class;

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
