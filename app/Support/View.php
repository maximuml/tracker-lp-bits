<?php

namespace App\Support;

/**
 * Legacy view-rendering helper extracted from `include/functions.php`.
 *
 * Backs `render()`. It is a thin wrapper around `extract()` + `require`
 * with output buffering; the wrapper keeps the `die()` side effect.
 */
final class View
{
    /**
     * Render a PHP view file.
     *
     * Mirrors `render()`. If `$return` is true the buffered output is
     * returned; otherwise the script dies after printing it.
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $view, array $data, bool $return, string $rootPath): mixed
    {
        extract($data);

        if (! file_exists($view)) {
            $view = $rootPath . $view;
        }

        if (substr($view, -4) !== '.php') {
            $view .= '.php';
        }

        ob_start();
        ob_implicit_flush(false);
        require $view;
        $result = ob_get_clean();

        if ($return) {
            return $result;
        }

        die($result);
    }
}
