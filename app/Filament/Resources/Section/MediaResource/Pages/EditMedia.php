<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\MediaResource\Pages;

use App\Filament\Resources\Section\CodecResource\Pages\EditCodec;
use App\Filament\Resources\Section\MediaResource;
use Filament\Actions\DeleteAction;

class EditMedia extends EditCodec
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
