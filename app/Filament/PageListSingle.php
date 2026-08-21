<?php

namespace App\Filament;

use Closure;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;

class PageListSingle extends ManageRecords
{
    protected Width|string|null $maxContentWidth = 'full';

    protected function getTableFiltersLayout(): FiltersLayout
    {
        return FiltersLayout::AboveContent;
    }

    protected function getTableRecordActionUsing(): ?Closure
    {
        return null;
    }
}
