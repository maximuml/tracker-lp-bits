<?php

declare(strict_types=1);

namespace App\Filament\Resources\Security\CheaterResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Security\CheaterResource;

class ListCheaters extends PageList
{
    protected static string $resource = CheaterResource::class;
}
