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
