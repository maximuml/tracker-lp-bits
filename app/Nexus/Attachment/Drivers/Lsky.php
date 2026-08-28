<?php

declare(strict_types=1);

namespace Nexus\Attachment\Drivers;

use App\Support\Config\SiteConfig;
use App\Support\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use Nexus\Attachment\Storage;

class Lsky extends Storage
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
        return static::DRIVER_LSKY;
    }
}
