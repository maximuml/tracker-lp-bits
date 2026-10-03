<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\OverForumResource\Pages;

use App\Filament\Resources\Section\OverForumResource;
use App\Repositories\OverforumRepository;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverForum extends EditRecord
{
    protected static string $resource = OverForumResource::class;

    /** @return array<DeleteAction> */
    private OverforumRepository $overforumRepository;

    public function boot(
        OverforumRepository $overforumRepository,
    ): void {
        $this->overforumRepository = $overforumRepository;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn ($record) => $this->overforumRepository->deleteOverforum($record->id)),
        ];
    }
}
