<?php

namespace App\Support;

/**
 * Stateless image-URL helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration.
 */
final class Image
{
    /**
     * Build a weserv.nl thumbnail URL for the given image.
     *
     * Mirrors `resize_image()`: requires a full URL with a scheme, then
     * appends width, height and fit query parameters when supplied.
     */
    public static function weserv(string $url, ?int $width = null, ?int $height = null, string $fit = 'cover'): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === false) {
            return $url;
        }

        $url = "$scheme://images.weserv.nl/?url=$url";
        if ($width !== null) {
            $url .= "&w=$width";
        }
        if ($height !== null) {
            $url .= "&h=$height";
        }
        $url .= "&fit=$fit";

        return $url;
    }
}
