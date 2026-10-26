<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Render-layer XSS regressions for Comment::format (via Format::formatComment)
 * — the path used to render forum signatures and BBCode bodies.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class CommentRenderSecurityTest extends TestCase
{
    /**
     * Signatures must be escaped at render time (stripHtml = true): raw
     * HTML stored in users.signature — e.g. written verbatim by the
     * staff modtask form — must never reach the output as markup.
     */
    public function test_signature_raw_html_is_escaped_when_strip_html_enabled(): void
    {
        $signature = '<script>alert(1)</script><b>hi</b>';
        $html = (string) Format::formatComment($signature, true);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $html);
    }

    /**
     * The [font=...] span_map replacement interpolates its capture into a
     * double-quoted attribute. The capture charset must not admit quotes —
     * [font=" onmouseover="alert(1)] used to produce a live event handler
     * (<span face="" onmouseover="alert(1)">) on any caller that renders
     * raw-HTML bodies (e.g. signatures before stripHtml was enabled).
     *
     * The leftover literal text `[font=" ...]` is inert — what must not
     * appear is an event handler inside a produced tag.
     */
    #[DataProvider('fontInjectionVectors')]
    public function test_font_bbcode_rejects_attribute_breakout(string $bbcode): void
    {
        $html = (string) Format::formatComment($bbcode.'x[/font]', false);

        $this->assertSame(0, preg_match('/<[^>]*onmouseover/i', $html),
            "font capture broke out of the attribute: $bbcode → $html");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function fontInjectionVectors(): array
    {
        return [
            'double quote breakout' => ['[font=" onmouseover="alert(1)]'],
            'single quote breakout' => ["[font=' onmouseover='alert(1)]"],
            'quote then equals' => ['[font="onmouseover=alert(1)]'],
        ];
    }

    /**
     * A benign font name still renders.
     */
    public function test_font_bbcode_renders_plain_family_names(): void
    {
        $html = (string) Format::formatComment('[font=Arial]x[/font]', false);

        $this->assertStringContainsString('<span face="Arial">x</span>', $html);
    }
}
