<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\AgentDenyResource\Pages;

use App\Filament\Resources\System\AgentDenyResource;
use App\Support\Cache;
use Filament\Resources\Pages\CreateRecord;

class CreateAgentDeny extends CreateRecord
{
    protected static string $resource = AgentDenyResource::class;

    public function afterCreate(): void
    {
        Cache::clearAgentAllowDeny();
    }
}
