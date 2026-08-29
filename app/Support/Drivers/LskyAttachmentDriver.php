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
 * Lsky Pro image-hosting driver.
 *
 * Uploads files via the Lsky REST API and returns the hosted URL.
 */
final class LskyAttachmentDriver implements AttachmentDriver
{
    public function upload(string $filepath): string
    {
        $api = SiteConfig::current()->imageHosting->lskyUploadApiEndpoint();
        $token = SiteConfig::current()->imageHosting->lskyUploadToken();
        $logPrefix = "filepath: $filepath, api: $api, token: $token";
        $httpClient = new Client;
        $response = $httpClient->request('POST', $api, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $token),
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen($filepath, 'r'),
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
        if (! isset($result['status'])) {
            Logger::writeWithContext((string) "{$logPrefix}, no status", (string) 'error', (bool) false);
            throw new \Exception('Unable to parse response body, no status');
        }
        if ($result['status'] !== true) {
            Logger::writeWithContext((string) "{$logPrefix}, status != true", (string) 'error', (bool) false);
            throw new \Exception('upload fail: '.$result['message']);
        }
        if (! isset($result['data']['links']['url'])) {
            Logger::writeWithContext((string) "{$logPrefix}, no links url", (string) 'error', (bool) false);
            throw new \Exception('upload fail: no links url');
        }

        return $result['data']['links']['url'];
    }

    public function getBaseUrl(): string
    {
        return SiteConfig::current()->imageHosting->lskyBaseUrl();
    }

    public function getDriverName(): string
    {
        return AttachmentStorage::DRIVER_LSKY;
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
