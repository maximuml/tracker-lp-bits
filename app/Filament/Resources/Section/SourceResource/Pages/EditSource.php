<?php

namespace App\Filament\Resources\Section\SourceResource\Pages;

use App\Filament\Resources\Section\CodecResource\Pages\EditCodec;
use App\Filament\Resources\Section\SourceResource;
use Filament\Actions\DeleteAction;

class EditSource extends EditCodec
{
    protected static string $resource = SourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
