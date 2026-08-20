<?php

namespace App\Filament\Resources\Section\StandardResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\StandardResource;
use App\Models\Standard;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListStandards extends PageList
{
    protected static string $resource = StandardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<Standard> */
    protected function getTableQuery(): Builder
    {
        return Standard::query()->with('search_box')->orderBy('mode', 'asc');
    }
}
