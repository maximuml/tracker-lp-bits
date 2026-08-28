<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\CategoryResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\CategoryResource;
use App\Models\Category;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListCategories extends PageList
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<Category> */
    protected function getTableQuery(): Builder
    {
        return Category::query()->with('search_box')->orderBy('mode', 'asc');
    }
}
