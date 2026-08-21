<?php

namespace App\Filament\Resources\Section\ForumResource\Pages;

use App\Filament\Resources\Section\ForumResource;
use App\Repositories\ForumRepository;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditForum extends EditRecord
{
    protected static string $resource = ForumResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn ($record) => app(ForumRepository::class)->deleteForum($record->id)),
        ];
    }
}
