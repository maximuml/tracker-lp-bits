<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\InviteResource\Pages;

use App\Filament\PageList;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\InviteResource;
use App\Models\Invite;
use Filament\Pages\Actions;
use Illuminate\Database\Eloquent\Builder;

class ListInvites extends PageList
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = InviteResource::class;

    /** @return list<string> */
    protected function userDisplayKeys(): array
    {
        return ['inviter'];
    }

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }

    /** @return Builder<Invite> */
    protected function getTableQuery(): Builder
    {
        return Invite::query()->with(['inviter_user']);
    }
}
