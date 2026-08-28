<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserMetaResource\Pages;

use App\Filament\Resources\User\UserMetaResource;
use Filament\Actions\Contracts\HasActions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserMeta extends CreateRecord implements HasActions
{
    protected static string $resource = UserMetaResource::class;
}
