<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\IpLogs\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\System\IpLogs\IpLogResource;
use Filament\Actions\CreateAction;

class ManageIpLogs extends PageListSingle
{
    protected static string $resource = IpLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            CreateAction::make(),
        ];
    }
}
