<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Support\SupportContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Generic bridge for legacy PHP partials that still contain database queries
 * and side effects. The partial view file is kept render-only and echoes the
 * captured content produced by a sibling `*_content.php` file.
 */
final class LegacyPartialRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|Response|RedirectResponse
     */
    public function render(string $name, array $data = []): array|Response|RedirectResponse
    {
        $path = __DIR__ . '/' . $name . '_content.php';

        if (! file_exists($path)) {
            return response('Legacy content missing: ' . $name, 500);
        }

        ob_start();
        try {
            $__renderer_path = $path;
            $__renderer_data = $data;
            unset($__renderer_data['__renderer_path'], $__renderer_data['__renderer_data']);
            $render = static function () use ($__renderer_path, $__renderer_data): void {
                extract(SupportContext::getGlobalsForView(), EXTR_SKIP);
                extract($__renderer_data);
                include $__renderer_path;
            };
            $render();
        } catch (HttpResponseException $e) {
            ob_get_clean();

            throw $e;
        }

        $content = (string) ob_get_clean();

        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url);
            }
        }

        return ['content' => $content];
    }
}
