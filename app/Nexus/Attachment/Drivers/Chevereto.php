<?php
namespace Nexus\Attachment\Drivers;

use GuzzleHttp\Psr7;
use Nexus\Attachment\Storage;

class Chevereto extends Storage {

    function upload(string $filepath): string
    {
        $api = \App\Support\Config\SiteConfig::current()->imageHosting->cheveretoUploadApiEndpoint();
        $token = \App\Support\Config\SiteConfig::current()->imageHosting->cheveretoUploadToken();
        $logPrefix = "filepath: $filepath, api: $api, token: $token";
        $httpClient = new \GuzzleHttp\Client();
        $response = $httpClient->request('POST', $api, [
            'headers' => [
                'X-API-Key' => sprintf('%s', $token),
            ],
            'multipart' => [
                [
                    'name'     => 'source',
                    'contents' => Psr7\Utils::tryFopen($filepath, 'r')
                ],
                [
                    'name'     => 'key',
                    'contents' => $token
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
        if (!isset($result["image"]["url"])) {
            \App\Support\Logger::writeWithContext((string) "{$logPrefix}, no image url", (string) "error", (bool) false);
            throw new \Exception("upload fail: " . ($result["error"]["message"] ?? ""));
        }
        $url = $result["image"]["url"];
        \App\Support\Logger::writeWithContext((string) "{$logPrefix}, upload success, url: {$url}", (string) 'info', (bool) false);
        return $url;
    }

    function getBaseUrl(): string
    {
        return \App\Support\Config\SiteConfig::current()->imageHosting->cheveretoBaseUrl();
    }

    function getDriverName(): string
    {
        return static::DRIVER_CHEVERETO;
    }
}
