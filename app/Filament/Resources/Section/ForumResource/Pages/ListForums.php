<?php

namespace App\Filament\Resources\Section\ForumResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\PageList;
use App\Filament\Resources\Section\ForumResource;
use Filament\Resources\Pages\ListRecords;

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
