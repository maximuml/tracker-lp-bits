<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\SourceResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\SourceResource;
use App\Models\Source;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListSources extends PageList
{
    protected static string $resource = SourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<Source> */
    protected function getTableQuery(): Builder
    {
        return Source::query()->with('search_box')->orderBy('mode', 'asc');
    }
}
