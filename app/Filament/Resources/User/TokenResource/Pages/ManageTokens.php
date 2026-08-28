<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\TokenResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\User\TokenResource;
use Filament\Actions;

class ManageTokens extends PageListSingle
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
