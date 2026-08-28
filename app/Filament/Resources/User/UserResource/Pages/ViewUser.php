<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\Resources\User\UserResource;
use Filament\Actions\Contracts\HasActions;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord implements HasActions
{
    protected static string $resource = UserResource::class;
}
