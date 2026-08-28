<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\ForumResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\ForumResource;
use Filament\Actions\CreateAction;

class ListForums extends PageList
{
    protected static string $resource = ForumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
