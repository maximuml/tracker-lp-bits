<?php

namespace App\Filament\Resources\Section\OverForumResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Section\OverForumResource;
use Filament\Resources\Pages\EditRecord;

class EditOverForum extends EditRecord
{
    protected static string $resource = OverForumResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn ($record) => app(\App\Repositories\ForumRepository::class)->deleteOverforum($record->id)),
        ];
    }
}
