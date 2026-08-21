<?php

namespace App\Filament\Resources\Section\ProcessingResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\ProcessingResource;
use App\Models\Processing;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListProcessings extends PageList
{
    protected static string $resource = ProcessingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<Processing> */
    protected function getTableQuery(): Builder
    {
        return Processing::query()->with('search_box')->orderBy('mode', 'asc');
    }
}
