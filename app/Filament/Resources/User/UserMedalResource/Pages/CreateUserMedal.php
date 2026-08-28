<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserMedalResource\Pages;

use App\Filament\Resources\User\UserMedalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserMedal extends CreateRecord
{
    protected static string $resource = UserMedalResource::class;
}
