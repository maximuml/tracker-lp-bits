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
        $__renderer_partial = base_path('resources/legacy/settings.php');
        $__renderer_data = $data;
        unset($__renderer_data['__renderer_partial'], $__renderer_data['__renderer_data']);
        if (! File::exists($__renderer_partial)) {
            return 'Legacy settings partial missing.';
        }
        $render = static function () use ($__renderer_partial, $__renderer_data): void {
            extract($__renderer_data);
            /** @noinspection PhpIncludeInspection */
            include $__renderer_partial;
        };
        ob_start();
        $render();
        return (string) ob_get_clean();
    }
}
