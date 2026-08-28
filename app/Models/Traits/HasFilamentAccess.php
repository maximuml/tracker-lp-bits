<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Support\Logger;
use Filament\Panel;

/**
 * Filament admin-panel integration: panel access and identity hooks.
 */
trait HasFilamentAccess
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canAccessAdmin();
    }

    public function getFilamentName(): string
    {
        return (string) $this->username;
    }

    public function canAccessAdmin(): bool
    {
        $targetClass = self::getAccessAdminClassMin();
        if (! $this->class || $this->class < $targetClass) {
            Logger::writeWithContext((string) sprintf('user: %s, no class or class < %s, can not access admin.', $this->id, $targetClass), (string) 'info', (bool) false);

            return false;
        }

        return true;
    }
}
