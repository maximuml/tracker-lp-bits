<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserMetaResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\UserMetaResource;
use App\Models\UserMeta;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Actions;
use Illuminate\Database\Eloquent\Builder;

class ListUserMetas extends PageList implements HasActions
{
    protected static string $resource = UserMetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }

    /** @return Builder<UserMeta> */
    protected function getTableQuery(): Builder
    {
        return UserMeta::query()->whereIn('meta_key', array_keys(UserMeta::$metaKeys));
    }
}
