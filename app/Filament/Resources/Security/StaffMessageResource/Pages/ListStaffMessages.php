<?php

namespace App\Filament\Resources\Security\StaffMessageResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Security\StaffMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListStaffMessages extends PageList
{
    protected static string $resource = StaffMessageResource::class;
}
