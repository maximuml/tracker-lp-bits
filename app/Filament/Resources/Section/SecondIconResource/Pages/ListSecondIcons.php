<?php

namespace App\Filament\Resources\Section\SecondIconResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\SecondIconResource;
use App\Models\SecondIcon;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListSecondIcons extends PageList
{
    protected static string $resource = SecondIconResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<SecondIcon> */
    protected function getTableQuery(): Builder
    {
        return SecondIcon::query()->with('search_box');
    }
}
