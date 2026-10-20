<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared pixel cap for every GD decode site (attachment/bitbucket
 * uploads, remote + local cover thumbnails).
 *
 * A forged image header (e.g. 30 000×30 000 PNG in ~40 KB) makes
 * imagecreatefrom* allocate gigabytes and kill the PHP worker — every
 * decode must validate declared dimensions first, before touching GD.
 */
final class ImageLimits
{
    public const MAX_PIXELS = 25_000_000;

    /**
     * getimagesize() dimensions for an in-memory blob, or null when the
     * data is not a valid image or its declared size exceeds MAX_PIXELS.
     *
     * @return array<int|string, int|string>|null
     */
    public static function sizeFromString(string $data): ?array
    {
        $size = @getimagesizefromstring($data);
        if ($size === false) {
            return null;
        }
        $width = (int) $size[0];
        $height = (int) $size[1];
        if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PIXELS) {
            return null;
        }

        return $size;
    }
}
