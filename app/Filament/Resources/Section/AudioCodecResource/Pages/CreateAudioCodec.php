<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\AudioCodecResource\Pages;

use App\Filament\Resources\Section\AudioCodecResource;
use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;

class CreateAudioCodec extends CreateCodec
{
    protected static string $resource = AudioCodecResource::class;
}
