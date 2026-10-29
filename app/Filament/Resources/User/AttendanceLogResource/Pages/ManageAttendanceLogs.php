<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\AttendanceLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\AttendanceLogResource;
use Filament\Actions\CreateAction;

class ManageAttendanceLogs extends PageListSingle
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = AttendanceLogResource::class;

    /** @return list<string> */
    protected function userDisplayKeys(): array
    {
        return ['uid'];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
