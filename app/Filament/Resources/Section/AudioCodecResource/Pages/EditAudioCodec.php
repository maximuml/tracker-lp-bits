<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\AudioCodecResource\Pages;

use App\Filament\Resources\Section\AudioCodecResource;
use App\Filament\Resources\Section\CodecResource\Pages\EditCodec;
use Filament\Actions\DeleteAction;

class EditAudioCodec extends EditCodec
{
    protected static string $resource = AudioCodecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
