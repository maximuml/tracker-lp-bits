<?php
namespace Nexus\Attachment\Drivers;

use GuzzleHttp\Psr7;
use Nexus\Attachment\Storage;

class Lsky extends Storage {

    function upload(string $filepath): string
    {
        $api = \App\Support\Config\SiteConfig::current()->imageHosting->lskyUploadApiEndpoint();
        $token = \App\Support\Config\SiteConfig::current()->imageHosting->lskyUploadToken();
        $logPrefix = "filepath: $filepath, api: $api, token: $token";
        $httpClient = new \GuzzleHttp\Client();
        $response = $httpClient->request('POST', $api, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $token),
            ],
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => Psr7\Utils::tryFopen($filepath, 'r')
                ]
            ]
        ]);
        $statusCode = $response->getStatusCode();
        $logPrefix .= ", status code: $statusCode";
        if ($statusCode != 200) {
            \App\Support\Logger::writeWithContext((string) "{$logPrefix}, statusCode != 200", (string) "error", (bool) false);
            throw new \Exception("Unable to upload file, status code {$statusCode}");
        }
        $stringBody = (string)$response->getBody();
        $logPrefix .= ", body: $stringBody";
        $result = json_decode($stringBody, true);
        if (!is_array($result)) {
            \App\Support\Logger::writeWithContext((string) "{$logPrefix}, can not parse to array", (string) "error", (bool) false);
            throw new \Exception("Unable to parse response body");
        }
        if (!isset($result["status"])) {
            \App\Support\Logger::writeWithContext((string) "{$logPrefix}, no status", (string) "error", (bool) false);
            throw new \Exception("Unable to parse response body, no status");
        }
        if ($result["status"] !== true) {
            \App\Support\Logger::writeWithContext((string) "{$logPrefix}, status != true", (string) "error", (bool) false);
            throw new \Exception("upload fail: " . $result["message"]);
        }
        if (!isset($result["data"]["links"]["url"])) {
            \App\Support\Logger::writeWithContext((string) "{$logPrefix}, no links url", (string) "error", (bool) false);
            throw new \Exception("upload fail: no links url");
        }

        return $result["data"]["links"]["url"];
    }

    function getBaseUrl(): string
    {
        return \App\Support\Config\SiteConfig::current()->imageHosting->lskyBaseUrl();
    }

    function getDriverName(): string
    {
        return static::DRIVER_LSKY;
    }
}
