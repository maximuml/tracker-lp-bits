<?php

namespace App\Support;

use App\Jobs\GenerateCoverThumbnail;
use App\Support\Cache\LegacyRedisCache;
use Nexus\Nexus;

/**
 * Cover-thumbnail URL resolver extracted from `include/functions.php`.
 *
 * Backs the legacy `cover_thumb_url()` global. The helper decides
 * whether a cached thumbnail exists, dispatches remote covers to the
 * `GenerateCoverThumbnail` queue job, and resizes local paths
 * synchronously.
 */
final class CoverThumb
{
    /**
     * @param  object|null  $cache  Legacy cache object with a public `$redis` property.
     */
    public static function url(
        string $url,
        int $maxWidth,
        int $maxHeight,
        int $quality,
        string $saveDir,
        string $httpDir,
        string $rootPath,
        ?object $cache = null,
    ): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (! extension_loaded('gd')) {
            return $url;
        }

        $key = md5($url.'|'.$maxWidth.'x'.$maxHeight);
        $relativeDir = 'covers/'.substr($key, 0, 2);
        $filename = $key.'.jpg';
        $absoluteDir = Path::makeFolder($saveDir.'/', $relativeDir, $rootPath);
        $absolutePath = rtrim($absoluteDir, '/').'/'.$filename;
        $publicUrl = $httpDir.'/'.$relativeDir.'/'.$filename;

        if (is_file($absolutePath) && filesize($absolutePath) > 0) {
            return $publicUrl;
        }

        if (preg_match('#^https?://#i', $url)) {
            self::dispatchRemote($url, $absolutePath, $maxWidth, $maxHeight, $quality, $cache);

            return $url;
        }

        return self::resizeLocal($url, $absolutePath, $maxWidth, $maxHeight, $quality, $rootPath, $publicUrl);
    }

    /**
     * Context-aware wrapper for {@see url()}.
     */
    public static function urlWithContext(string $url, int $maxWidth = 240, int $maxHeight = 360, int $quality = 82): string
    {
        $saveDirectory = (string) SupportContext::getGlobal('savedirectory_attachment', '');
        $httpDirectory = (string) SupportContext::getGlobal('httpdirectory_attachment', '');

        return self::url(
            $url,
            $maxWidth,
            $maxHeight,
            $quality,
            $saveDirectory ?: 'attachments',
            $httpDirectory ?: 'attachments',
            defined('ROOT_PATH') ? (string) ROOT_PATH : '',
            app(LegacyRedisCache::class) ?? null,
        );
    }

    private static function dispatchRemote(
        string $url,
        string $absolutePath,
        int $maxWidth,
        int $maxHeight,
        int $quality,
        ?object $cache,
    ): void {
        $lockSet = false;
        if ($cache !== null && property_exists($cache, 'redis')) {
            $lockKey = 'cover_thumb:'.$absolutePath;
            $lockSet = (bool) $cache->redis->set($lockKey, 1, ['nx', 'ex' => 300]);
        }

        if ($lockSet) {
            Nexus::dispatchQueueJob(new GenerateCoverThumbnail($url, $absolutePath, $maxWidth, $maxHeight, $quality));
        }
    }

    private static function resizeLocal(
        string $url,
        string $absolutePath,
        int $maxWidth,
        int $maxHeight,
        int $quality,
        string $rootPath,
        string $publicUrl,
    ): string {
        $localPath = $rootPath.ltrim($url, '/');
        if (! is_file($localPath)) {
            return $url;
        }

        $data = @file_get_contents($localPath);
        if ($data === false) {
            return $url;
        }

        $src = @imagecreatefromstring($data);
        if (! $src) {
            return $url;
        }

        $srcWidth = imagesx($src);
        $srcHeight = imagesy($src);

        $scale = min(1.0, $maxWidth / $srcWidth, $maxHeight / $srcHeight);
        $dstWidth = max(1, (int) floor($srcWidth * $scale));
        $dstHeight = max(1, (int) floor($srcHeight * $scale));
        $dst = imagecreatetruecolor($dstWidth, $dstHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
        $ok = @imagejpeg($dst, $absolutePath, max(1, min(100, $quality)));
        imagedestroy($src);
        imagedestroy($dst);

        if (! $ok) {
            return $url;
        }

        return $publicUrl;
    }
}
