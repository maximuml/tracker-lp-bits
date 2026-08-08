<?php

namespace App\Support;

/**
 * Media/inline HTML renderers extracted from `include/functions.php`.
 *
 * Backs the legacy `formatUrl()`, `formatImg()`, `formatFlash()`,
 * `formatYoutube()`, `formatAudio()`, `formatSpoiler()` and `formatHidden()`
 * helpers. Lives under `App\Support` and delegates to the more specific
 * `Html`, `Comment` and `BBCode` helpers.
 */
final class HtmlRenderer
{
    public static function formatUrl(string $url, bool $newWindow = false, string $text = '', string $linkClass = ''): string
    {
        return Html::formatUrl($url, $newWindow, $text, $linkClass);
    }

    public static function formatImg(string $src, bool $enableImageResizer, int $image_max_width, int $image_max_height, string $imgId = ""): string
    {
        return Html::formatImg($src, $enableImageResizer, $image_max_width, $image_max_height, $imgId);
    }

    public static function formatFlash(string $src, int|string $width, int|string $height): string
    {
        return Html::formatFlash($src, $width, $height);
    }

    public static function formatYoutube(string $src, int|string $width = '', int|string $height = ''): string
    {
        return Html::formatYoutube($src, $width, $height);
    }

    public static function formatAudio(string $src): string
    {
        return Html::formatAudio($src);
    }

    public static function formatSpoiler(string $content, string $title = '', bool $defaultCollapsed = true): string
    {
        return Html::formatSpoiler($content, $title, $defaultCollapsed);
    }

    public static function formatHidden(string $content): string
    {
        return Comment::addTempCode(BBCode::hidden($content));
    }
}
