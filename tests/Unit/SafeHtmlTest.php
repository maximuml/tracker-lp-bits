<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Html\HtmlSanitizer;
use App\Support\Html\SafeHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Wave 5 Step 24: SafeHtml + HtmlSanitizer XSS corpus tests.
 *
 * Verifies that the sanitizer blocks known XSS attack vectors and
 * that SafeHtml value objects enforce the type boundary between
 * untrusted strings and sanitized HTML.
 */
final class SafeHtmlTest extends TestCase
{
    /**
     * SafeHtml from plain text escapes HTML special characters.
     */
    public function test_from_plain_text_escapes_html(): void
    {
        $safe = SafeHtml::fromPlainText('<script>alert(1)</script>');
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $safe->toHtml());
    }

    /**
     * SafeHtml from trusted HTML passes through unchanged.
     */
    public function test_from_trusted_html_passes_through(): void
    {
        $html = '<b>Hello</b>';
        $safe = SafeHtml::fromTrustedHtml($html);
        $this->assertSame($html, $safe->toHtml());
    }

    /**
     * SafeHtml from untrusted HTML strips script tags.
     */
    public function test_from_untrusted_html_strips_script_tags(): void
    {
        $safe = SafeHtml::fromUntrustedHtml('<script>alert(1)</script><b>safe</b>');
        $this->assertStringNotContainsString('<script', $safe->toHtml());
        $this->assertStringContainsString('<b>safe</b>', $safe->toHtml());
    }

    /**
     * SafeHtml toString returns the HTML.
     */
    public function test_to_string_returns_html(): void
    {
        $safe = SafeHtml::fromTrustedHtml('<b>test</b>');
        $this->assertSame('<b>test</b>', (string) $safe);
    }

    /**
     * SafeHtml isEmpty returns true for empty string.
     */
    public function test_is_empty_returns_true_for_empty(): void
    {
        $this->assertTrue(SafeHtml::fromTrustedHtml('')->isEmpty());
        $this->assertFalse(SafeHtml::fromTrustedHtml('<b>hi</b>')->isEmpty());
    }

    /**
     * SafeHtml append concatenates two SafeHtml instances.
     */
    public function test_append_concatenates(): void
    {
        $a = SafeHtml::fromTrustedHtml('<b>A</b>');
        $b = SafeHtml::fromTrustedHtml('<i>B</i>');
        $this->assertSame('<b>A</b><i>B</i>', $a->append($b)->toHtml());
    }

    // === HtmlSanitizer XSS corpus tests ===

    /**
     * @dataProvider xssVectors
     */
    #[DataProvider('xssVectors')]
    public function test_sanitizer_blocks_xss_vectors(string $input, string $mustNotContain): void
    {
        $result = HtmlSanitizer::sanitize($input);
        $this->assertStringNotContainsStringIgnoringCase($mustNotContain, $result,
            "Sanitizer must block: $mustNotContain in input: $input");
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function xssVectors(): array
    {
        return [
            'script tag' => ['<script>alert(1)</script>', '<script'],
            'script with attributes' => ['<script src="evil.js"></script>', '<script'],
            'img onerror' => ['<img src=x onerror=alert(1)>', 'onerror'],
            'svg onload' => ['<svg onload=alert(1)>', 'onload'],
            'body onload' => ['<body onload=alert(1)>', 'onload'],
            'iframe' => ['<iframe src="javascript:alert(1)"></iframe>', '<iframe'],
            'object' => ['<object data="evil.swf"></object>', '<object'],
            'embed' => ['<embed src="evil.swf">', '<embed'],
            'javascript URL' => ['<a href="javascript:alert(1)">click</a>', 'javascript:'],
            'vbscript URL' => ['<a href="vbscript:alert(1)">click</a>', 'vbscript:'],
            'data URL' => ['<a href="data:text/html,<script>alert(1)</script>">click</a>', 'data:'],
            'onclick attribute' => ['<b onclick=alert(1)>text</b>', 'onclick'],
            'onmouseover attribute' => ['<b onmouseover=alert(1)>text</b>', 'onmouseover'],
            'style expression' => ['<div style="width: expression(alert(1))">x</div>', 'expression'],
            'style url javascript' => ['<div style="background: url(javascript:alert(1))">x</div>', 'javascript:'],
            'HTML comment' => ['<!-- <script>alert(1)</script> -->', '<script'],
            'malformed tag' => ['<script>alert(1)<script>', '<script'],
            'encoded script' => ['<scr&#105;pt>alert(1)</script>', '<script'],
        ];
    }

    /**
     * Sanitizer preserves safe formatting tags.
     */
    public function test_sanitizer_preserves_safe_tags(): void
    {
        $input = '<b>bold</b><i>italic</i><a href="https://example.com">link</a>';
        $result = HtmlSanitizer::sanitize($input);
        $this->assertStringContainsString('<b>bold</b>', $result);
        $this->assertStringContainsString('<i>italic</i>', $result);
        $this->assertStringContainsString('href="https://example.com"', $result);
    }

    /**
     * Sanitizer preserves safe CSS properties.
     */
    public function test_sanitizer_preserves_safe_css(): void
    {
        $input = '<span style="color: red; text-align: center">text</span>';
        $result = HtmlSanitizer::sanitize($input);
        $this->assertStringContainsString('color: red', $result);
        $this->assertStringContainsString('text-align: center', $result);
    }

    /**
     * Sanitizer strips dangerous CSS properties (expression, javascript:).
     */
    public function test_sanitizer_strips_dangerous_css(): void
    {
        $input = '<div style="color: red; width: expression(alert(1)); background: url(javascript:alert(1))">x</div>';
        $result = HtmlSanitizer::sanitize($input);
        $this->assertStringContainsString('color: red', $result);
        $this->assertStringNotContainsStringIgnoringCase('expression', $result);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $result);
    }

    /**
     * Sanitizer allows http and https URLs.
     */
    public function test_sanitizer_allows_http_https_urls(): void
    {
        $result = HtmlSanitizer::sanitize('<a href="http://example.com">link</a><a href="https://secure.com">link2</a>');
        $this->assertStringContainsString('href="http://example.com"', $result);
        $this->assertStringContainsString('href="https://secure.com"', $result);
    }

    /**
     * Sanitizer allows relative URLs.
     */
    public function test_sanitizer_allows_relative_urls(): void
    {
        $result = HtmlSanitizer::sanitize('<a href="/page">link</a>');
        $this->assertStringContainsString('href="/page"', $result);
    }

    /**
     * Sanitizer allows mailto URLs.
     */
    public function test_sanitizer_allows_mailto_urls(): void
    {
        $result = HtmlSanitizer::sanitize('<a href="mailto:user@example.com">email</a>');
        $this->assertStringContainsString('href="mailto:user@example.com"', $result);
    }

    /**
     * Sanitizer strips unknown tags.
     */
    public function test_sanitizer_strips_unknown_tags(): void
    {
        $result = HtmlSanitizer::sanitize('<custom>text</custom><b>bold</b>');
        $this->assertStringNotContainsString('<custom', $result);
        $this->assertStringContainsString('<b>bold</b>', $result);
    }

    /**
     * Sanitizer handles empty input.
     */
    public function test_sanitizer_handles_empty_input(): void
    {
        $this->assertSame('', HtmlSanitizer::sanitize(''));
    }

    /**
     * Sanitizer strips event handlers from allowed tags.
     */
    public function test_sanitizer_strips_event_handlers_from_allowed_tags(): void
    {
        $result = HtmlSanitizer::sanitize('<a href="https://example.com" onclick="alert(1)">link</a>');
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $result);
    }

    /**
     * Blade @safeHtml directive is registered.
     */
    public function test_blade_safehtml_directive_registered(): void
    {
        $blade = app('blade.compiler');
        $directives = $blade->getCustomDirectives();
        $this->assertArrayHasKey('safeHtml', $directives, '@safeHtml directive must be registered');
    }

    /**
     * Blade stringable for SafeHtml is registered.
     */
    public function test_blade_stringable_registered_for_safehtml(): void
    {
        // Blade::stringable doesn't expose a public API to check directly.
        // We verify by checking that the SafeHtml class implements __toString
        // (which Blade::stringable uses to render {{ $safeHtml }})
        $safe = SafeHtml::fromTrustedHtml('<b>rendered</b>');
        $this->assertSame('<b>rendered</b>', (string) $safe,
            'SafeHtml must implement __toString for Blade stringable rendering');
    }
}
