<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateCoverThumbnail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class GenerateCoverThumbnailSsrfTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function blockedUrlProvider(): array
    {
        return [
            'file scheme' => ['file:///etc/passwd'],
            'gopher scheme' => ['gopher://example.com/'],
            'localhost' => ['http://localhost/a.jpg'],
            'loopback v4' => ['http://127.0.0.1/a.jpg'],
            'private v4' => ['http://10.0.0.5/a.jpg'],
            'metadata ip' => ['http://169.254.169.254/latest/meta-data'],
            'metadata name' => ['http://metadata.google.internal/'],
            'loopback v6' => ['http://[::1]/a.jpg'],
            'mapped v6' => ['http://[::ffff:127.0.0.1]/a.jpg'],
            'no host' => ['http:///a.jpg'],
        ];
    }

    #[DataProvider('blockedUrlProvider')]
    public function test_blocks_internal_urls(string $url): void
    {
        $this->assertFalse(GenerateCoverThumbnail::isAllowedUrl($url));
    }

    public function test_allows_public_ip_literal(): void
    {
        $this->assertTrue(GenerateCoverThumbnail::isAllowedUrl('https://8.8.8.8/a.jpg'));
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function resolveEntryProvider(): array
    {
        return [
            'https default port' => ['https://img.example.com/a.jpg', '93.184.216.34', 'img.example.com:443:93.184.216.34'],
            'http default port' => ['http://img.example.com/a.jpg', '93.184.216.34', 'img.example.com:80:93.184.216.34'],
            'explicit port' => ['http://img.example.com:8080/a.jpg', '93.184.216.34', 'img.example.com:8080:93.184.216.34'],
            'ipv6 address bracketed' => ['https://img.example.com/a.jpg', '2606:2800::1', 'img.example.com:443:[2606:2800::1]'],
            'ip literal needs no pin' => ['https://8.8.8.8/a.jpg', '8.8.8.8', null],
        ];
    }

    #[DataProvider('resolveEntryProvider')]
    public function test_curl_resolve_entry_pins_validated_ip(string $url, string $ip, ?string $expected): void
    {
        $method = new \ReflectionMethod(GenerateCoverThumbnail::class, 'curlResolveEntry');

        $this->assertSame($expected, $method->invoke(null, $url, $ip));
    }
}
