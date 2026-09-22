<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
class UrlNormalizeTest extends TestCase
{
    public function test_full_https_url_is_kept(): void
    {
        $this->assertSame('https://example.com', Url::normalize('https://example.com', false));
    }

    public function test_full_http_url_is_kept_even_when_secure(): void
    {
        $this->assertSame('http://example.com', Url::normalize('http://example.com', true));
    }

    public function test_host_only_gets_explicit_scheme(): void
    {
        $this->assertSame('https://example.com', Url::normalize('example.com', true));
        $this->assertSame('http://example.com', Url::normalize('example.com', false));
    }

    public function test_host_only_with_path_and_port(): void
    {
        $this->assertSame('http://example.com:8080/announce.php', Url::normalize('example.com:8080/announce.php', false));
    }

    public function test_scheme_relative_input(): void
    {
        $this->assertSame('https://example.com/x', Url::normalize('//example.com/x', true));
    }

    public function test_trailing_slash_and_case_are_canonicalized(): void
    {
        $this->assertSame('https://example.com', Url::normalize('HTTPS://EXAMPLE.COM/', true));
        $this->assertSame('https://example.com/base', Url::normalize('https://example.com/base/', true));
    }

    public function test_ipv6_host_with_port(): void
    {
        $this->assertSame('http://[::1]:8080', Url::normalize('[::1]:8080', false));
        $this->assertSame('http://[::1]:8080/x', Url::normalize('http://[::1]:8080/x', true));
    }

    public function test_whitespace_is_trimmed(): void
    {
        $this->assertSame('https://example.com', Url::normalize("  https://example.com  \n", true));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'no host' => ['http://'],
            'scheme only' => ['https://'],
            'disallowed scheme' => ['ftp://example.com'],
            'javascript scheme' => ['javascript://alert(1)'],
            'userinfo' => ['http://user:pass@example.com'],
            'query' => ['http://example.com/?a=b'],
            'fragment' => ['http://example.com/#frag'],
            'non-numeric port' => ['http://example.com:abc'],
            'out-of-range port' => ['http://example.com:99999'],
            'space in host' => ['http://exa mple.com'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function test_invalid_values_return_null(string $raw): void
    {
        $this->assertNull(Url::normalize($raw, true));
        $this->assertNull(Url::normalize($raw, false));
    }

    public function test_absolute_returns_normalized_string(): void
    {
        $this->assertSame('https://example.com/path', Url::absolute('example.com/path', true));
    }

    public function test_absolute_returns_empty_for_invalid(): void
    {
        $this->assertSame('', Url::absolute('javascript://x', true));
        $this->assertSame('', Url::absolute('', false));
    }

    public function test_absolute_never_produces_double_scheme(): void
    {
        $this->assertSame('https://example.com', Url::absolute('https://example.com', false));
        $this->assertSame('http://example.com', Url::absolute('http://example.com', true));
    }
}
