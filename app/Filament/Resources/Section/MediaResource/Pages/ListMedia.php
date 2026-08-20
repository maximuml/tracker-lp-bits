<?php

namespace App\Filament\Resources\Section\MediaResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\MediaResource;
use App\Models\Media;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListMedia extends PageList
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<Media> */
    protected function getTableQuery(): Builder
    {
        return Media::query()->with('search_box')->orderBy('mode', 'asc');
    }
}
