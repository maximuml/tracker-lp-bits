<?php

namespace App\Support;

/**
 * Description-AST helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * Backs the legacy `get_image_from_description($arr, $first, $useDefault)`
 * which had a dual-shape return type — `string` when `$first === true`,
 * `list<string>` otherwise. The dual shape is split into two
 * single-typed methods here; the proxy in `include/functions.php`
 * still dispatches by the legacy `$first` flag and supplies the
 * (in practice dead-code) `nophoto.gif` default URL when asked.
 *
 * The "description AST" is the array form torrents store after the
 * description field is parsed — each node looks like
 * `['type' => 'image'|'attachment'|'text'|..., 'data' => ['url' => ...]]`.
 * Only `image` and `attachment` nodes have a meaningful URL; the rest
 * are skipped.
 *
 * Legacy quirks preserved bit-for-bit:
 *  - A node whose URL coerces to false via PHP's `!$url` (i.e. empty
 *    string OR the literal string `"0"`) is skipped — same as the
 *    original `if (!$url) continue;` branch.
 *  - Malformed nodes (non-array entries, missing `type`, non-array
 *    `data`, non-string `url`) are skipped rather than fatal-erroring.
 *    The original would PHP-notice-then-skip on missing `type`/`data`
 *    and fatal on a non-array `$value`; tightening this to "skip
 *    gracefully" is the only intentional behaviour widening here.
 */
final class Description
{
    private const IMAGE_TYPES = ['attachment', 'image'];

    /**
     * Collect every non-empty image / attachment URL in document order.
     *
     * @param  array<int|string, mixed>  $descriptionArr
     * @return list<string>
     */
    public static function imageUrls(array $descriptionArr): array
    {
        $images = [];
        foreach ($descriptionArr as $value) {
            $url = self::extractUrl($value);
            if ($url === '') {
                continue;
            }
            $images[] = $url;
        }

        return $images;
    }

    /**
     * Return the first non-empty image / attachment URL, or
     * `$defaultUrl` if none was found. Used as the torrent "cover".
     *
     * @param  array<int|string, mixed>  $descriptionArr
     */
    public static function firstImageUrl(array $descriptionArr, string $defaultUrl = ''): string
    {
        foreach ($descriptionArr as $value) {
            $url = self::extractUrl($value);
            if ($url === '') {
                continue;
            }

            return $url;
        }

        return $defaultUrl;
    }

    private static function extractUrl(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }
        $type = $value['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::IMAGE_TYPES, true)) {
            return '';
        }
        $data = $value['data'] ?? null;
        if (! is_array($data)) {
            return '';
        }
        $url = $data['url'] ?? '';
        if (! is_string($url)) {
            return '';
        }
        if (! $url) {
            return '';
        }

        return $url;
    }
}
