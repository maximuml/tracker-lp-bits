<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SafeHtml;
use App\Support\SanitizedHtml;
use Tests\TestCase;

final class SafeHtmlTest extends TestCase
{
    public function test_strips_script_tags(): void
    {
        $input = '<script>alert("xss")</script><p>hello</p>';
        $result = SafeHtml::sanitize($input);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function test_strips_event_handlers(): void
    {
        $input = '<p onclick="alert(1)">text</p>';
        $result = SafeHtml::sanitize($input);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('text', $result);
    }

    public function test_strict_preset_allows_basic_elements(): void
    {
        $input = '<b>bold</b> <i>italic</i> <a href="https://example.com">link</a>';
        $result = SafeHtml::sanitize($input);

        $this->assertStringContainsString('<b>bold</b>', $result);
        $this->assertStringContainsString('<i>italic</i>', $result);
        $this->assertStringContainsString('example.com', $result);
    }

    public function test_strict_preset_strips_images(): void
    {
        $input = '<img src="https://example.com/x.png" alt="test">';
        $result = SafeHtml::sanitize($input, 'strict');

        $this->assertStringNotContainsString('<img', $result);
    }

    public function test_comment_preset_allows_images(): void
    {
        $input = '<img src="https://example.com/x.png" alt="test">';
        $result = SafeHtml::sanitize($input, 'comment');

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('example.com', $result);
    }

    public function test_media_preset_allows_tables(): void
    {
        $input = '<table><tr><td>cell</td></tr></table>';
        $result = SafeHtml::sanitize($input, 'media');

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('cell', $result);
    }

    public function test_strips_javascript_urls(): void
    {
        $input = '<a href="javascript:alert(1)">click</a>';
        $result = SafeHtml::sanitize($input);

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_strips_iframes(): void
    {
        $input = '<iframe src="https://evil.com"></iframe><p>safe</p>';
        $result = SafeHtml::sanitize($input);

        $this->assertStringNotContainsString('<iframe', $result);
        $this->assertStringContainsString('safe', $result);
    }

    public function test_empty_input_returns_empty(): void
    {
        $this->assertSame('', SafeHtml::sanitize(''));
    }

    public function test_make_returns_sanitized_html_vo(): void
    {
        $vo = SafeHtml::make('<p>hello</p><script>bad</script>');

        $this->assertInstanceOf(SanitizedHtml::class, $vo);
        $this->assertStringNotContainsString('<script', (string) $vo);
        $this->assertStringContainsString('hello', $vo->value());
        $this->assertSame((string) $vo, $vo->value());
    }

    public function test_force_https_upgrades_http_urls(): void
    {
        $input = '<a href="http://example.com">link</a>';
        $result = SafeHtml::sanitize($input);

        $this->assertStringContainsString('https://example.com', $result);
    }
}
