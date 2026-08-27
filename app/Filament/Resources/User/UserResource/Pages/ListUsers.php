<?php

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\UserResource;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Tables\Enums\FiltersLayout;

class ListUsers extends PageList implements HasActions
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableFiltersLayout(): FiltersLayout
    {
        return FiltersLayout::AboveContent;
    }
}
