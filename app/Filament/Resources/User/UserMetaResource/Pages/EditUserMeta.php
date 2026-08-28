<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserMetaResource\Pages;

use App\Filament\Resources\User\UserMetaResource;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserMeta extends EditRecord implements HasActions
{
    protected static string $resource = UserMetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
