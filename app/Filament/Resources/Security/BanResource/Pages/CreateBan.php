<?php

namespace App\Filament\Resources\Security\BanResource\Pages;

use App\Filament\Resources\Security\BanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBan extends CreateRecord
{
    protected static string $resource = BanResource::class;

    public function afterCreate(): void
    {
        \App\Support\Cache::clearAgentAllowDeny();
    }
}
