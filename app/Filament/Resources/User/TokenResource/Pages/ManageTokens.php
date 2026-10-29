<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\TokenResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\TokenResource;
use Filament\Actions;

class ManageTokens extends PageListSingle
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = TokenResource::class;

    protected function userDisplayKey(): ?string
    {
        return 'tokenable_id';
    }

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
