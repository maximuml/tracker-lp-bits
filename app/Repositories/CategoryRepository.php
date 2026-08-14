<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\File;

/**
 * Bridge for legacy category management pages until full Blade migration.
 */
final class CategoryRepository
{
    /**
     * Render the legacy catmanage page.
     *
     * @param array<string, mixed> $data
     */
    public static function render(array $data = []): string
    {
        extract($data);
        $partial = base_path('resources/legacy/catmanage.php');
        if (! File::exists($partial)) {
            return 'Legacy catmanage partial missing.';
        }
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $partial;
        return (string) ob_get_clean();
    }
}
