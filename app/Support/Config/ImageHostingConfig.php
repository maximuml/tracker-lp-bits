<?php

declare(strict_types=1);

namespace App\Support\Config;

final class ImageHostingConfig extends Config
{
    public function driver(string $default = 'local'): string
    {
        return $this->string('driver', $default);
    }

    public function cheveretoUploadApiEndpoint(string $default = ''): string
    {
        $value = $this->data['chevereto']['upload_api_endpoint'] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function cheveretoUploadToken(string $default = ''): string
    {
        $value = $this->data['chevereto']['upload_token'] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function cheveretoBaseUrl(string $default = ''): string
    {
        $value = $this->data['chevereto']['base_url'] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function lskyUploadApiEndpoint(string $default = ''): string
    {
        $value = $this->data['lsky']['upload_api_endpoint'] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function lskyUploadToken(string $default = ''): string
    {
        $value = $this->data['lsky']['upload_token'] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function lskyBaseUrl(string $default = ''): string
    {
        $value = $this->data['lsky']['base_url'] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }
}
