<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\SectionResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\SectionResource;
use Filament\Actions\CreateAction;

class ListSections extends PageList
{
    protected static string $resource = SectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
