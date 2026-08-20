<?php

namespace App\Filament\Resources\Security\LoginAttemptResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Security\LoginAttemptResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginAttempts extends PageList
{
    protected static string $resource = LoginAttemptResource::class;
}
