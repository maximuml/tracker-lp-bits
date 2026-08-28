<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\CodecResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\CodecResource;
use App\Models\Codec;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListCodecs extends PageList
{
    protected static string $resource = CodecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<Codec> */
    protected function getTableQuery(): Builder
    {
        return Codec::query()->with('search_box')->orderBy('mode', 'asc');
    }
}
