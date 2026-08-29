<?php

declare(strict_types=1);

namespace App\Support\Drivers;

use App\Support\AttachmentDriver;
use App\Support\AttachmentStorage;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;

/**
 * Chevereto image-hosting driver.
 *
 * Uploads files via the Chevereto REST API and returns the hosted URL.
 */
final class CheveretoAttachmentDriver implements AttachmentDriver
{
    public function upload(string $filepath): string
    {
        $api = SiteConfig::current()->imageHosting->cheveretoUploadApiEndpoint();
        $token = SiteConfig::current()->imageHosting->cheveretoUploadToken();
        $logPrefix = "filepath: $filepath, api: $api, token: $token";
        $httpClient = new Client;
        $response = $httpClient->request('POST', $api, [
            'headers' => [
                'X-API-Key' => sprintf('%s', $token),
            ],
            'multipart' => [
                [
                    'name' => 'source',
                    'contents' => Psr7\Utils::tryFopen($filepath, 'r'),
                ],
                [
                    'name' => 'key',
                    'contents' => $token,
                ],
            ],
        ]);
        $statusCode = $response->getStatusCode();
        $logPrefix .= ", status code: $statusCode";
        if ($statusCode != 200) {
            Logger::writeWithContext((string) "{$logPrefix}, statusCode != 200", (string) 'error', (bool) false);
            throw new \Exception("Unable to upload file, status code {$statusCode}");
        }
        $stringBody = (string) $response->getBody();
        $logPrefix .= ", body: $stringBody";
        $result = json_decode($stringBody, true);
        if (! is_array($result)) {
            Logger::writeWithContext((string) "{$logPrefix}, can not parse to array", (string) 'error', (bool) false);
            throw new \Exception('Unable to parse response body');
        }
        if (! isset($result['image']['url'])) {
            Logger::writeWithContext((string) "{$logPrefix}, no image url", (string) 'error', (bool) false);
            throw new \Exception('upload fail: '.($result['error']['message'] ?? ''));
        }
        $url = $result['image']['url'];
        Logger::writeWithContext((string) "{$logPrefix}, upload success, url: {$url}", (string) 'info', (bool) false);

        return $url;
    }

    public function getBaseUrl(): string
    {
        return SiteConfig::current()->imageHosting->cheveretoBaseUrl();
    }

    public function getDriverName(): string
    {
        return AttachmentStorage::DRIVER_CHEVERETO;
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
