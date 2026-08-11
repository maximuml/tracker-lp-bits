<?php

namespace App\Support;

/**
 * Attachment HTML emitter extracted from `include/functions.php`.
 *
 * Backs the legacy `print_attachment()` global. The database/cache lookup
 * and the logging stay in the procedural proxy; this class owns the
 * pure string assembly and icon mapping so it can be unit-tested.
 */
final class Attachment
{
    /**
     * @param  array<string, mixed>  $row         Attachment row from the DB.
     * @param  array<string, string> $labels      Localised labels:
     *                                            'size', 'downloads'.
     */
    public static function render(
        array $row,
        string $dlkey,
        bool $enableImage,
        bool $imageResizer,
        string $url,
        string $sizeText,
        string $timeText,
        array $labels,
    ): string {
        $id = (int) ($row['id'] ?? 0);
        $filename = (string) ($row['filename'] ?? '');

        if (($row['isimage'] ?? 0) == 1 && $enableImage) {
            return self::renderImage($id, $filename, $url, $imageResizer, $sizeText, $timeText, (string) ($labels['size'] ?? ''));
        }

        return self::renderFile($row, $id, $dlkey, $filename, $sizeText, $timeText, (string) ($labels['downloads'] ?? ''));
    }

    private static function renderImage(
        int $id,
        string $filename,
        string $url,
        bool $imageResizer,
        string $sizeText,
        string $timeText,
        string $sizeLabel,
    ): string {
        $onclick = $imageResizer ? ' data-zoomable data-zoom-src="'.htmlspecialchars($url).'"' : '';
        $tooltip = htmlspecialchars("<strong>$sizeLabel</strong>: $sizeText<br />$timeText");

        return '<img id="attach'.$id.'" style="max-width: 700px" alt="'.htmlspecialchars($filename).'" src="'.htmlspecialchars($url).'"'.$onclick.' onmouseover="domTT_activate(this, event, \'content\', \''.$tooltip.'\', \'styleClass\', \'attach\', \'x\', findPosition(this)[0], \'y\', findPosition(this)[1]-58);" />';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function renderFile(
        array $row,
        int $id,
        string $dlkey,
        string $filename,
        string $sizeText,
        string $timeText,
        string $downloadsLabel,
    ): string {
        $icon = self::iconForFileType((string) ($row['filetype'] ?? ''));
        $downloadCount = number_format((int) ($row['downloads'] ?? 0));
        $href = htmlspecialchars("getattachment.php?id=$id&dlkey=$dlkey");
        $filenameHtml = htmlspecialchars($filename);
        $tooltip = htmlspecialchars("<strong>$downloadsLabel</strong>: $downloadCount<br />$timeText");

        return '<div class="attach">'.$icon.'&nbsp;&nbsp;<a href="'.$href.'" target="_blank" id="attach'.$id.'" onmouseover="domTT_activate(this, event, \'content\', \''.$tooltip.'\', \'styleClass\', \'attach\', \'x\', findPosition(this)[0], \'y\', findPosition(this)[1]-58);">'.$filenameHtml.'</a>&nbsp;&nbsp;<font class="size">('.$sizeText.')</font></div>';
    }

    /**
     * Fetch an attachment row by dlkey (with one-hour file cache) and
     * return the public URL for its content/thumbnail.
     *
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    public static function rowAndUrlByKey(string $dlkey): array
    {
        $httpdirectory = \App\Support\Config\SiteConfig::current()->attachment->httpDirectory();
        $row = \Nexus\Database\NexusDB::cache_get('attachment_' . $dlkey . '_content');

        if (empty($row) && strlen($dlkey) == 32) {
            $result = \Nexus\Database\NexusDB::table('attachments')->where('dlkey', $dlkey)->first();
            $row = $result ? (array) $result : null;
            \Nexus\Database\NexusDB::cache_put('attachment_' . $dlkey . '_content', $row, 86400);
        }

        if (empty($row)) {
            return [null, ''];
        }

        $driver = $row['driver'] ?? 'local';
        if ($driver == 'local') {
            if (($row['thumb'] ?? 0) == 1) {
                $url = $httpdirectory . '/' . $row['location'] . '.thumb.jpg';
            } else {
                $url = $httpdirectory . '/' . $row['location'];
            }
        } else {
            $url = \Nexus\Attachment\Storage::getDriver($driver)->getImageUrl($row['location']);
        }

        \do_log(sprintf('driver: %s, location: %s, url: %s', $driver, $row['location'], $url));

        return [$row, $url];
    }

    /**
     * Full `print_attachment()` flow: lookup by dlkey, build the public
     * URL and render the HTML fragment. Returns a not-found marker when
     * the key is invalid.
     */
    public static function renderByKey(string $dlkey, bool $enableImage = true, bool $imageResizer = true): string
    {
        [$row, $url] = self::rowAndUrlByKey($dlkey);

        if (empty($row)) {
            return '<div style="text-decoration: line-through; font-size: 7pt">' . \nexus_trans('attachment.text_key') . $dlkey . \nexus_trans('attachment.not_found') . '</div>';
        }

        return self::render(
            $row,
            $dlkey,
            $enableImage,
            $imageResizer,
            $url,
            \App\Support\Format::size($row['filesize']),
            \App\Support\Time::format($row['added']),
            [
                'size' => \nexus_trans('attachment.size'),
                'downloads' => \nexus_trans('attachment.downloads'),
            ]
        );
    }

    /**
     * Extract the storage key from an attachment URL.
     *
     * Mirrors `attachmentKey()`.
     */
    public static function keyFromUrl(string $url): string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("URL: '$url' invalid.");
        }

        $parsed = parse_url($url);
        $driver = config('admin.upload.disk');

        return match ($driver) {
            'qiniu' => trim($parsed['path'] ?? '', '/'),
            'cloudinary' => (function () use ($parsed) {
                $parts = explode('/', $parsed['path'] ?? '');
                $key = end($parts);
                if (\Illuminate\Support\Str::contains($key, '.')) {
                    $key = strstr($key, '.', true);
                }
                return $key;
            })(),
            default => throw new \RuntimeException('不支持的云盘驱动'),
        };
    }

    /**
     * Build the public URL for an attachment location.
     *
     * Mirrors `attachmentUrl()`.
     */
    public static function publicUrl(string $location): string
    {
        return sprintf('%s/attachments/%s', \getSchemeAndHttpHost(), trim($location, '/'));
    }

    /**
     * Replace `[attach]dlkey[/attach]` tags with `[img]url[/img]` tags.
     *
     * Mirrors `bbcode_attach_to_img()`.
     */
    public static function bbcodeToImg(string $text): string
    {
        $pattern = '/\[attach\]([0-9a-zA-z][0-9a-zA-z]*)\[\/attach\]/is';

        return preg_replace_callback($pattern, function ($matches) {
            $dlkey = $matches[1];
            $httpdirectory = \App\Support\Config\SiteConfig::current()->attachment->httpDirectory();
            $row = \Nexus\Database\NexusDB::remember('attachment_' . $dlkey . '_content', 86400, function () use ($dlkey) {
                $record = \App\Models\Attachment::query()->where('dlkey', $dlkey)->first();

                return $record ? $record->toArray() : [];
            });

            if (empty($row) || ($row['isimage'] ?? 0) != 1) {
                \do_log(sprintf('dlkey: %s get attachment %s not exists or not image', $dlkey, json_encode($row)));
                return $matches[0];
            }

            $driver = $row['driver'] ?? 'local';
            if ($driver === 'local') {
                $url = $httpdirectory . '/' . $row['location'];
                if (($row['thumb'] ?? 0) == 1) {
                    $url .= '.thumb.jpg';
                }
                $url = sprintf('%s/%s', \getSchemeAndHttpHost(true), trim($url, '/'));
            } else {
                $url = \Nexus\Attachment\Storage::getDriver($driver)->getImageUrl($row['location']);
            }

            return '[img]' . $url . '[/img]';
        }, $text, 20);
    }

    private static function iconForFileType(string $filetype): string
    {
        return match ($filetype) {
            'application/x-bittorrent' => '<img alt="torrent" src="pic/attachicons/torrent.gif" />',
            'application/zip',
            'application/rar',
            'application/x-7z-compressed',
            'application/x-gzip' => '<img alt="archive" src="pic/attachicons/archive.gif" />',
            'audio/mpeg',
            'audio/ogg' => '<img alt="audio" src="pic/attachicons/audio.gif" />',
            'video/x-flv' => '<img alt="flv" src="pic/attachicons/flv.gif" />',
            default => '<img alt="other" src="pic/attachicons/common.gif" />',
        };
    }
}
