<?php

declare(strict_types=1);

namespace App\Filament;

trait EditRedirectIndexTrait
{
    protected function getRedirectUrl(): ?string
    {
        return static::$resource::getUrl('index');
    }
}
