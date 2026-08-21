<?php

namespace App\Filament\Resources\Section\OverForumResource\Pages;

use App\Filament\Resources\Section\OverForumResource;
use App\Repositories\ForumRepository;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverForum extends EditRecord
{
    protected static string $resource = OverForumResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn ($record) => app(ForumRepository::class)->deleteOverforum($record->id)),
        ];
    }
}
