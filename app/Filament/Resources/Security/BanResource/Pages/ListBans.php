<?php

declare(strict_types=1);

namespace App\Filament\Resources\Security\BanResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Security\BanResource;
use Filament\Actions\CreateAction;

class ListBans extends PageList
{
    protected static string $resource = BanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
