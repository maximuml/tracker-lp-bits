<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\HitAndRunResource\Pages;

use App\Filament\PageList;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\HitAndRunResource;
use Filament\Pages\Actions;

class ListHitAndRuns extends PageList
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = HitAndRunResource::class;

    protected function userDisplayKey(): ?string
    {
        return 'uid';
    }

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
