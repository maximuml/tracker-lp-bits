<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\MediaResource\Pages;

use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;
use App\Filament\Resources\Section\MediaResource;

class CreateMedia extends CreateCodec
{
    protected static string $resource = MediaResource::class;
}
