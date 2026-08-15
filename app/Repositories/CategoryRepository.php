<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\SupportContext;

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
        $partial = __DIR__ . '/../Services/Legacy/catmanage_content.php';
        if (! is_file($partial)) {
            return 'Legacy catmanage partial missing.';
        }

        extract(SupportContext::getGlobalsForView());
        extract($data);

        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $partial;

        return (string) ob_get_clean();
    }
}
