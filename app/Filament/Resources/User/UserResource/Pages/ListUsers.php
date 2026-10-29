<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\PageList;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\UserResource;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Tables\Enums\FiltersLayout;

class ListUsers extends PageList implements HasActions
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = UserResource::class;

    /** @return list<string> */
    protected function userDisplayKeys(): array
    {
        return ['id'];
    }

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
