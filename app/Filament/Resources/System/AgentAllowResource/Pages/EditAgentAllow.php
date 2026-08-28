<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use App\Filament\Resources\System\AgentAllowResource;
use App\Support\Cache;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgentAllow extends EditRecord
{
    protected static string $resource = AgentAllowResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(function ($record) {
                $record->delete();
                Cache::clearAgentAllowDeny();

                return redirect(AgentAllowResource::getUrl());
            }),
        ];
    }

    public function afterSave(): void
    {
        Cache::clearAgentAllowDeny();
    }
}
