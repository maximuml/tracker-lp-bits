<?php

namespace App\Filament\Resources\Section\CategoryResource\Pages;

use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\CategoryResource;
use App\Support\Cache;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @see functions.php::get_category_row()
     */
    protected function afterSave(): void
    {
        Cache::clearCategory();
    }
}
