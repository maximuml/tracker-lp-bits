<?php

namespace Tests\Unit\Support;

use App\Support\Http\SafeReturnUrl;
use PHPUnit\Framework\TestCase;

class SafeReturnUrlTest extends TestCase
{
    public function test_empty_returns_fallback(): void
    {
        $this->assertSame('/messages.php', SafeReturnUrl::filter('', '/messages.php'));
    }

    public function test_default_fallback_is_root(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter(''));
    }

    public function test_relative_path_passes(): void
    {
        $this->assertSame('/messages.php?out=1', SafeReturnUrl::filter('messages.php?out=1'));
    }

    public function test_path_with_leading_slash_passes(): void
    {
        $this->assertSame('/torrents.php?cat=12', SafeReturnUrl::filter('/torrents.php?cat=12'));
    }

    public function test_query_only_passes(): void
    {
        $this->assertSame('/?foo=bar', SafeReturnUrl::filter('?foo=bar'));
    }

    public function test_absolute_https_rejected(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter('https://evil.example.com/path'));
    }

    public function test_absolute_http_rejected(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter('http://evil.example.com/path'));
    }

    public function test_protocol_relative_rejected(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter('//evil.example.com/path'));
    }

    public function test_backslash_protocol_relative_rejected(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter('/\\evil.example.com/path'));
    }

    public function test_leading_backslash_rejected(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter('\\evil.example.com'));
    }

    public function test_javascript_scheme_rejected(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter('javascript:alert(1)'));
    }

    public function test_data_scheme_rejected(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter('data:text/html,<script>alert(1)</script>'));
    }

    public function test_uppercase_scheme_rejected(): void
    {
        $this->assertSame('/', SafeReturnUrl::filter('HTTPS://evil.example.com'));
    }

    public function test_fallback_is_normalised(): void
    {
        $this->assertSame('/messages.php', SafeReturnUrl::filter('https://evil', 'messages.php'));
    }

    public function test_relative_path_without_leading_slash_gets_one(): void
    {
        $this->assertSame('/index.php', SafeReturnUrl::filter('index.php'));
    }
}
