<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\ProcessingResource\Pages;

use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;
use App\Filament\Resources\Section\ProcessingResource;

class CreateProcessing extends CreateCodec
{
    protected static string $resource = ProcessingResource::class;
}
