<?php

declare(strict_types=1);

namespace App\Support\Drivers;

use App\Support\AttachmentDriver;
use App\Support\AttachmentStorage;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\Url;

/**
 * Local filesystem attachment driver.
 *
 * Files are served from the configured HTTP attachment directory; no
 * remote upload is performed.
 */
final class LocalAttachmentDriver implements AttachmentDriver
{
    public function upload(string $filepath): string
    {
        throw new \RuntimeException('Not implemented');
    }

    public function getBaseUrl(): string
    {
        return sprintf('%s/%s', Url::schemeAndHost(false), trim(SiteConfig::current()->attachment->httpDirectory(), '/'));
    }

    public function getDriverName(): string
    {
        return AttachmentStorage::DRIVER_LOCAL;
    }

    public function uploadGetLocation(string $filepath, string $originalName): string
    {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $newFilepath = sprintf('%s/%s', dirname($filepath), trim($originalName));
            $moveResult = move_uploaded_file($filepath, $newFilepath);
            Logger::writeWithContext((string) sprintf('filepath: %s, newFilepath: %s, moveResult: %s', $filepath, $newFilepath, $moveResult), (string) 'info', (bool) false);
            if (! $moveResult) {
                throw new \Exception('Failed to move uploaded file.');
            }
            $url = $this->upload($newFilepath);
            @unlink($newFilepath);
        } else {
            $url = $this->upload($filepath);
            @unlink($filepath);
        }

        return $this->trimBaseUrl($url);
    }

    public function getImageUrl(string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        return sprintf('%s/%s', trim($this->getBaseUrl(), '/'), trim($location, '/'));
    }

    private function trimBaseUrl(string $url): string
    {
        $baseUrl = trim($this->getBaseUrl(), '/').'/';
        if (str_starts_with($url, $baseUrl)) {
            return substr($url, strlen($baseUrl));
        }

        return $url;
    }
}
