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
     * Parse a BBCode-style torrent description into an AST of typed nodes.
     *
     * Mirrors `format_description()`: resolves `[attach]` tags to attachment
     * URLs, normalises `[quote=...]` to `[quote]`, collapses nested quotes,
     * then splits the description into attachment/image/url/quote/text nodes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function parse(string $description): array
    {
        // Resolve [attach] placeholders to attachment URLs.
        $pattern = '/(\[attach\](.*)\[\/attach\])/isU';
        $matchCount = preg_match_all($pattern, $description, $matches);
        if ($matchCount) {
            $attachments = \App\Repositories\AttachmentRepository::findByDlkeys($matches[2]);
            if (! empty($attachments)) {
                $description = (string) preg_replace_callback($pattern, function ($matches) use ($attachments) {
                    $item = $attachments[$matches[2]] ?? null;
                    if ($item === null) {
                        return $matches[1];
                    }
                    $url = \Nexus\Attachment\Storage::getDriver($item['driver'])->getImageUrl($item['location']);
                    Logger::writeWithContext(sprintf('location: %s, driver: %s, url: %s', $item['location'], $item['driver'], $url));

                    return str_replace($matches[2], $url, $matches[1]);
                }, $description);
            }
        }

        // Normalise [quote=...] to [quote].
        $description = (string) preg_replace_callback('/\[quote=.*\]/isU', function () {
            return '[quote]';
        }, $description);

        // Collapse nested quotes into a single level.
        $delimiter = '__CYLX__';
        $description = (string) preg_replace_callback('/(\[quote\]){2,}(((?!\[quote\]).)*)\[\/quote\]/isU', function () use ($delimiter) {
            return $delimiter;
        }, $description);

        $description = (string) preg_replace_callback("/$delimiter(((?!\[quote\]).)+)\[\/quote\]/is", function ($matches) use ($delimiter) {
            $arr = array_reverse(explode('[/quote]', $matches[0]));
            foreach ($arr as $value) {
                $value = trim(str_replace($delimiter, '', $value));
                if (! empty($value)) {
                    return "[quote]{$value}[/quote]";
                }
            }

            return '';
        }, $description);

        // Split protected blocks from the rest of the text.
        $attachPattern = '\[attach\].*\[\/attach\]';
        $imgPattern = '\[img\].*\[\/img\]';
        $imgPattern2 = '\[img=.*\]';
        $urlPattern = '\[url=.*\].*\[\/url\]';
        $quotePattern = '\[quote.*\].*\[\/quote\]';
        $splitPattern = "/($attachPattern)|($imgPattern)|($imgPattern2)|($urlPattern)|($quotePattern)/isU";
        $delimiter = '{{{}}}';
        $description = (string) preg_replace_callback($splitPattern, function ($matches) use ($delimiter) {
            return $delimiter . $matches[0] . $delimiter;
        }, $description);

        $descriptionArr = preg_split("/[$delimiter]+/", $description);
        if ($descriptionArr === false) {
            $descriptionArr = [];
        }
        $results = [];
        foreach ($descriptionArr as $item) {
            if (preg_match('/\[attach\](.*)\[\/attach\]/isU', $item, $matches)) {
                $results[] = ['type' => 'attachment', 'data' => ['url' => $matches[1]]];
            } elseif (preg_match('/\[img\](.*)\[\/img\]/isU', $item, $matches)) {
                $results[] = ['type' => 'image', 'data' => ['url' => $matches[1]]];
            } elseif (preg_match('/\[img=(.*)\]/isU', $item, $matches)) {
                $results[] = ['type' => 'image', 'data' => ['url' => $matches[1]]];
            } elseif (preg_match('/\[url=(.*)\](.*)\[\/url\]/isU', $item, $matches)) {
                $results[] = ['type' => 'url', 'data' => ['url' => $matches[1], 'text' => \App\Support\Strings::stripAllTags($matches[2])]];
            } elseif (preg_match('/\[quote=?(.*)\](.*)\[\/quote\]/isU', $item, $matches)) {
                $results[] = ['type' => 'quote', 'data' => ['quote_text' => $matches[1], 'text' => \App\Support\Strings::stripAllTags($matches[2])]];
            } elseif (! empty($item)) {
                $results[] = ['type' => 'text', 'data' => ['text' => \App\Support\Strings::stripAllTags($item)]];
            }
        }

        return $results;
    }

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
     * Pick image URLs from a parsed description, optionally returning
     * only the first one with a configured default fallback.
     *
     * Backs the legacy `get_image_from_description()` helper.
     *
     * @param  array<int, array<string, mixed>>  $descriptionArr
     * @return array<int, string>|string
     */
    public static function imageFromDescription(array $descriptionArr, bool $first = false, bool $useDefault = true): array|string
    {
        if ($first) {
            $defaultUrl = $useDefault ? Url::schemeAndHost() . '/pic/nophoto.gif' : '';

            return self::firstImageUrl($descriptionArr, $defaultUrl);
        }

        return self::imageUrls($descriptionArr);
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
