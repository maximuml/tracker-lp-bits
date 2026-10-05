<?php

declare(strict_types=1);

namespace App\Support\Torrent;

/**
 * Maps a filename to its small colored extension badge — shared by the
 * viewfilelist AJAX endpoint and the Livewire TorrentFileList.
 */
final class FileBadge
{
    /**
     * @return array{cat: string, label: string}
     */
    public static function forFilename(string $filename): array
    {
        $dot = strrpos($filename, '.');
        $ext = $dot !== false ? strtolower(substr($filename, $dot + 1)) : '';
        if ($ext === '' || strlen($ext) > 5 || ! ctype_alnum($ext)) {
            return ['cat' => 'other', 'label' => '?'];
        }

        return ['cat' => self::extCategory($ext), 'label' => strtoupper($ext)];
    }

    private static function extCategory(string $ext): string
    {
        static $map = [
            'video' => ['mkv', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'ts', 'm2ts', 'mts', 'webm', 'mpg', 'mpeg', 'vob', 'rm', 'rmvb', 'm4v', '3gp', 'ogv', 'asf', 'divx', 'mxf'],
            'audio' => ['mp3', 'flac', 'wav', 'ogg', 'm4a', 'aac', 'opus', 'wma', 'ape', 'alac', 'dts', 'ac3', 'mka', 'mp2', 'mid', 'midi', 'tak', 'tta', 'wv'],
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'heic', 'heif', 'ico', 'psd', 'raw', 'arw', 'cr2', 'nef'],
            'subtitle' => ['srt', 'ass', 'ssa', 'sub', 'idx', 'vtt', 'sup', 'smi', 'sbv'],
            'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'zst', 'lz', 'lzma', 'tbz2', 'tgz', 'txz', 'cab', 'arj'],
            'iso' => ['iso', 'img', 'mds', 'mdf', 'bin', 'cue', 'nrg', 'dmg', 'vhd', 'vmdk'],
            'document' => ['pdf', 'epub', 'mobi', 'azw', 'azw3', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf', 'djvu', 'fb2', 'chm', 'odt', 'ods', 'odp'],
            'text' => ['txt', 'md', 'log', 'sfv', 'md5', 'sha1', 'sha256', 'par', 'par2', 'json', 'xml', 'yaml', 'yml', 'csv', 'ini'],
            'nfo' => ['nfo'],
            'code' => ['php', 'js', 'ts', 'py', 'rb', 'go', 'rs', 'c', 'h', 'cpp', 'hpp', 'cs', 'java', 'sh', 'sql', 'html', 'css', 'scss', 'vue'],
            'exec' => ['exe', 'msi', 'app', 'deb', 'rpm', 'apk', 'dmg', 'pkg', 'run', 'bat', 'cmd', 'ps1', 'jar'],
            'torrent' => ['torrent'],
        ];
        foreach ($map as $cat => $list) {
            if (in_array($ext, $list, true)) {
                return $cat;
            }
        }

        return 'other';
    }
}
