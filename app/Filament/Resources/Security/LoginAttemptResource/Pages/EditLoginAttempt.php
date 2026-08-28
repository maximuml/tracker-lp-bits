<?php

declare(strict_types=1);

namespace App\Filament\Resources\Security\LoginAttemptResource\Pages;

use App\Filament\Resources\Security\LoginAttemptResource;
use Filament\Resources\Pages\EditRecord;

class EditLoginAttempt extends EditRecord
{
    protected static string $resource = LoginAttemptResource::class;
}
