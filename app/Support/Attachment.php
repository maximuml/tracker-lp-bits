<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\AttachmentRepositoryInterface;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\Cache;

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
     * @param  array<string, mixed>  $row  Attachment row from the DB.
     * @param  array<string, string>  $labels  Localised labels:
     *                                         'size', 'downloads'.
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

        return trim(view('support._attach-img', [
            'id' => $id,
            'filename' => $filename,
            'url' => $url,
            'onclick' => SafeHtml::fromTrustedHtml($onclick),
            'sizeLabel' => $sizeLabel,
            'sizeText' => $sizeText,
            'timeText' => SafeHtml::fromTrustedHtml($timeText),
        ])->render());
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
        $href = "/web/getattachment?id=$id&dlkey=$dlkey";

        return trim(view('support._attach-file', [
            'icon' => SafeHtml::fromTrustedHtml($icon),
            'href' => $href,
            'id' => $id,
            'filename' => $filename,
            'downloadsLabel' => $downloadsLabel,
            'downloadCount' => $downloadCount,
            'timeText' => SafeHtml::fromTrustedHtml($timeText),
            'sizeText' => $sizeText,
        ])->render());
    }

    /**
     * Fetch an attachment row by dlkey (with one-hour file cache) and
     * return the public URL for its content/thumbnail.
     *
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    public static function rowAndUrlByKey(string $dlkey): array
    {
        $httpdirectory = SiteConfig::current()->attachment->httpDirectory();
        $row = Cache::get('attachment_'.$dlkey.'_content');

        if (empty($row) && strlen($dlkey) == 32) {
            $row = self::attachmentRepository()->findByDlkey($dlkey);
            Cache::put('attachment_'.$dlkey.'_content', $row, 86400);
        }

        if (empty($row)) {
            return [null, ''];
        }

        $driver = $row['driver'] ?? 'local';
        if ($driver == 'local') {
            if (($row['thumb'] ?? 0) == 1) {
                $url = $httpdirectory.'/'.$row['location'].'.thumb.jpg';
            } else {
                $url = $httpdirectory.'/'.$row['location'];
            }
        } else {
            $url = AttachmentStorage::driver($driver)->getImageUrl($row['location']);
        }

        Logger::writeWithContext(sprintf('driver: %s, location: %s, url: %s', $driver, $row['location'], $url));

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
            return trim(view('support._attach-notfound', [
                'before' => Locale::trans('attachment.text_key'),
                'dlkey' => $dlkey,
                'after' => Locale::trans('attachment.not_found'),
            ])->render());
        }

        return self::render(
            $row,
            $dlkey,
            $enableImage,
            $imageResizer,
            $url,
            Format::size($row['filesize']),
            (string) Time::format($row['added']),
            [
                'size' => Locale::trans('attachment.size'),
                'downloads' => Locale::trans('attachment.downloads'),
            ]
        );
    }

    /**
     * Replace `[attach]dlkey[/attach]` tags with `[img]url[/img]` tags.
     *
     * Mirrors `bbcode_attach_to_img()`.
     */
    public static function bbcodeToImg(string $text): string
    {
        $pattern = '/\[attach\]([0-9a-zA-z][0-9a-zA-z]*)\[\/attach\]/is';

        return (string) preg_replace_callback($pattern, function ($matches) {
            $dlkey = $matches[1];
            $httpdirectory = SiteConfig::current()->attachment->httpDirectory();
            $cached = Cache::get('attachment_'.$dlkey.'_content');
            $row = is_array($cached) ? $cached : (self::attachmentRepository()->findByDlkey($dlkey) ?? []);
            Cache::put('attachment_'.$dlkey.'_content', $row, 86400);

            if (empty($row) || ($row['isimage'] ?? 0) != 1) {
                Logger::writeWithContext(sprintf('dlkey: %s get attachment %s not exists or not image', $dlkey, Json::encode($row)));

                return $matches[0];
            }

            $driver = $row['driver'] ?? 'local';
            if ($driver === 'local') {
                $url = $httpdirectory.'/'.$row['location'];
                if (($row['thumb'] ?? 0) == 1) {
                    $url .= '.thumb.jpg';
                }
                $url = sprintf('%s/%s', Url::schemeAndHost(true), trim($url, '/'));
            } else {
                $url = AttachmentStorage::driver($driver)->getImageUrl($row['location']);
            }

            return '[img]'.$url.'[/img]';
        }, $text, 20);
    }

    private static function iconForFileType(string $filetype): string
    {
        [$alt, $icon] = match ($filetype) {
            'application/x-bittorrent' => ['torrent', 'torrent'],
            'application/zip',
            'application/rar',
            'application/x-7z-compressed',
            'application/x-gzip' => ['archive', 'archive'],
            'audio/mpeg',
            'audio/ogg' => ['audio', 'audio'],
            'video/x-flv' => ['flv', 'flv'],
            default => ['other', 'common'],
        };

        return trim(view('support._attach-icon', ['alt' => $alt, 'icon' => $icon])->render());
    }

    private static function attachmentRepository(): AttachmentRepositoryInterface
    {
        return app(AttachmentRepositoryInterface::class);
    }
}
