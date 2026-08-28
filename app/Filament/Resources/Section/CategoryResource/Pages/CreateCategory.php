<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\CategoryResource\Pages;

use App\Filament\CreateRedirectIndexTrait;
use App\Filament\Resources\Section\CategoryResource;
use App\Support\Cache;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use CreateRedirectIndexTrait;

    protected static string $resource = CategoryResource::class;

    protected function afterCreate(): void
    {
        Cache::clearCategory();
    }
}
