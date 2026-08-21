<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\SupportContext;
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

        $__renderer_path = $path;
        $__renderer_data = $data;
        unset($__renderer_data['__renderer_path'], $__renderer_data['__renderer_data']);
        $render = static function () use ($__renderer_path, $__renderer_data): void {
            extract(SupportContext::getGlobalsForView(), EXTR_SKIP);
            extract($__renderer_data);
            include $__renderer_path;
        };
        ob_start();
        $render();

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
