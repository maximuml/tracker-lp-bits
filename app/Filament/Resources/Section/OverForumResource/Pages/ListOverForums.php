<?php

namespace App\Filament\Resources\Section\OverForumResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\PageList;
use App\Filament\Resources\Section\OverForumResource;
use Filament\Resources\Pages\ListRecords;

class ListOverForums extends PageList
{
    protected static string $resource = OverForumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
