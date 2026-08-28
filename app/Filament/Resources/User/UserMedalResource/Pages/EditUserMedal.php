<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserMedalResource\Pages;

use App\Filament\Resources\User\UserMedalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserMedal extends EditRecord
{
    protected static string $resource = UserMedalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
