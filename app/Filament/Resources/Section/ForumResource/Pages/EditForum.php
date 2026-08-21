<?php

namespace App\Filament\Resources\Section\ForumResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Section\ForumResource;
use Filament\Resources\Pages\EditRecord;

class EditForum extends EditRecord
{
    protected static string $resource = ForumResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn ($record) => app(\App\Repositories\ForumRepository::class)->deleteForum($record->id)),
        ];
    }
}
