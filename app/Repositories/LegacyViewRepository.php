<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class LegacyViewRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $partial, array $data = []): string
    {
        $path = base_path('app/Services/Legacy/partials/'.ltrim($partial, '/').'.php');
        if (! File::exists($path)) {
            return 'Legacy partial missing: '.$partial;
        }

        extract(\App\Support\SupportContext::getGlobalsForView(), EXTR_SKIP);
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    public static function partialNameFromViewPath(string $viewPath): string
    {
        // e.g. "messages/_messages.blade.php" -> "messages"
        //      "my/_bonus.blade.php" -> "my_bonus"
        $dir = dirname($viewPath);
        $base = basename($viewPath, '.blade.php');
        $base = str_replace('_', '', $base); // _messages -> messages

        return Str::replace('/', '_', $dir).($base ? '_'.$base : '');
    }
}
