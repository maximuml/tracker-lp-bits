<?php

declare(strict_types=1);

namespace App\Filament;

use Closure;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Model;

class PageList extends ListRecords
{
    protected Width|string|null $maxContentWidth = 'full';

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return function (Model $record): ?string {
            return null;
        };
    }

    protected function getTableFiltersLayout(): FiltersLayout
    {
        return FiltersLayout::AboveContent;
    }
}
