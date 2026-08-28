<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\StandardResource\Pages;

use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;
use App\Filament\Resources\Section\StandardResource;

class CreateStandard extends CreateCodec
{
    protected static string $resource = StandardResource::class;
}
