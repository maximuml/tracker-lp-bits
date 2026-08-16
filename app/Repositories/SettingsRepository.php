<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\File;

/**
 * Bridge for legacy website settings admin page until full Blade migration.
 */
final class SettingsRepository
{
    /**
     * Render the legacy settings page.
     *
     * @param array<string, mixed> $data
     */
    public static function render(array $data = []): string
    {
        extract($data, EXTR_SKIP);
        $partial = base_path('resources/legacy/settings.php');
        if (! File::exists($partial)) {
            return 'Legacy settings partial missing.';
        }
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $partial;
        return (string) ob_get_clean();
    }
}
