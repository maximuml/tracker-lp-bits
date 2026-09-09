<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Html;

use App\Support\Html\SafeHtml;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class SafeHtmlTest extends TestCase
{
    public function test_from_trusted_html_preserves_markup(): void
    {
        $safe = SafeHtml::fromTrustedHtml('<b>bold</b>');
        $this->assertSame('<b>bold</b>', $safe->toHtml());
        $this->assertSame('<b>bold</b>', (string) $safe);
    }

    public function test_from_plain_text_escapes_special_chars(): void
    {
        $safe = SafeHtml::fromPlainText('<script>alert("x")</script>');
        $this->assertStringNotContainsString('<script>', $safe->toHtml());
        $this->assertStringContainsString('&lt;script&gt;', $safe->toHtml());
    }

    public function test_from_untrusted_html_sanitizes(): void
    {
        $safe = SafeHtml::fromUntrustedHtml('<script>alert(1)</script><b>ok</b>');
        $this->assertStringNotContainsString('<script>', $safe->toHtml());
        $this->assertStringContainsString('<b>ok</b>', $safe->toHtml());
    }

    public function test_is_empty(): void
    {
        $this->assertTrue(SafeHtml::fromTrustedHtml('')->isEmpty());
        $this->assertFalse(SafeHtml::fromTrustedHtml('x')->isEmpty());
    }

    public function test_append_concatenates(): void
    {
        $a = SafeHtml::fromTrustedHtml('<b>A</b>');
        $b = SafeHtml::fromTrustedHtml('<i>B</i>');
        $this->assertSame('<b>A</b><i>B</i>', $a->append($b)->toHtml());
    }
}
