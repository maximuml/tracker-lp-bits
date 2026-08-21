<?php

namespace App\Filament\Resources\Section\ProcessingResource\Pages;

use App\Filament\Resources\Section\CodecResource\Pages\EditCodec;
use App\Filament\Resources\Section\ProcessingResource;
use Filament\Actions\DeleteAction;

class EditProcessing extends EditCodec
{
    protected static string $resource = ProcessingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
