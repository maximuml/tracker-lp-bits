<?php

namespace App\Filament\Resources\System\ActivityLogs\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\System\ActivityLogs\ActivityLogResource;

class ManageActivityLogs extends PageListSingle
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
