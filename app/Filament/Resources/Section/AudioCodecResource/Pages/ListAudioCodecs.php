<?php

namespace App\Filament\Resources\Section\AudioCodecResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\AudioCodecResource;
use App\Models\AudioCodec;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListAudioCodecs extends PageList
{
    protected static string $resource = AudioCodecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<AudioCodec> */
    protected function getTableQuery(): Builder
    {
        return AudioCodec::query()->with('search_box')->orderBy('mode', 'asc');
    }
}
