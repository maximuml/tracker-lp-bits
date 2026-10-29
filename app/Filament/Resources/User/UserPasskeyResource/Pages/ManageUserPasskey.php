<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserPasskeyResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\UserPasskeyResource;

class ManageUserPasskey extends PageListSingle
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = UserPasskeyResource::class;

    /** @return list<string> */
    protected function userDisplayKeys(): array
    {
        return ['user_id'];
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
