<?php

namespace App\Filament\Resources\Torrent\TagResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Torrent\TagResource;
use App\Models\Tag;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListTags extends PageList
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<Tag> */
    protected function getTableQuery(): Builder
    {
        return Tag::query()->withCount('torrents')->withSum('torrents', 'size');
    }
}
