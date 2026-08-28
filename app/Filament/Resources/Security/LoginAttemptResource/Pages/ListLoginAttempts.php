<?php

declare(strict_types=1);

namespace App\Filament\Resources\Security\LoginAttemptResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Security\LoginAttemptResource;

class ListLoginAttempts extends PageList
{
    protected static string $resource = LoginAttemptResource::class;
}
