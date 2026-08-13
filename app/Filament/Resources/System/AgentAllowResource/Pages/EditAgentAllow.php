<?php

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\System\AgentAllowResource;
use App\Models\NexusModel;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentAllow extends EditRecord
{
    protected static string $resource = AgentAllowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(function ($record) {
                $record->delete();
                \App\Support\Cache::clearAgentAllowDeny();
                return redirect(AgentAllowResource::getUrl());
            })
        ];
    }

    public function afterSave()
    {
        \App\Support\Cache::clearAgentAllowDeny();
    }
}
