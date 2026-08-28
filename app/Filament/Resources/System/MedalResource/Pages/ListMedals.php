<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\MedalResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\MedalResource;
use App\Models\Medal;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListMedals extends PageList
{
    protected static string $resource = MedalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Builder<Medal> */
    protected function getTableQuery(): Builder
    {
        return Medal::query()->withCount('users');
    }
}
