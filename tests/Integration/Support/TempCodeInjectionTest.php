<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Format;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression tests for tempCode placeholder injection into attribute
 * positions.
 *
 * `[code]` blocks are replaced by `\x08tempCode_N\x08` placeholders early
 * in Comment::format(), and resolveTempCodes() substitutes them blindly
 * across the final string — including inside `href="…"`/`src="…"` of
 * other generated elements. A placeholder smuggled into a URL argument
 * must be defused, not resolved inside the attribute.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TempCodeInjectionTest extends TestCase
{
    public function test_code_placeholder_inside_url_attribute_is_defused(): void
    {
        $html = (string) Format::formatComment('[url=[code]X[/code]]click[/url]');

        $this->assertStringNotContainsString('codetop', $html);
        $this->assertStringNotContainsString('href="<', $html);
    }

    public function test_raw_tempcode_bytes_inside_url_attribute_are_defused(): void
    {
        $html = (string) Format::formatComment("[url=http://a.com/\x08tempCode_0\x08]x[/url]");

        $this->assertStringNotContainsString("\x08", $html);
        $this->assertStringNotContainsString('href="http://a.com/<', $html);
    }

    public function test_code_placeholder_inside_img_src_is_rejected(): void
    {
        $html = (string) Format::formatComment('[img=[code]X[/code]]');

        $this->assertStringNotContainsString('codetop', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_nested_img_inside_url_text_still_works(): void
    {
        $html = (string) Format::formatComment('[url=https://a.com][img]https://a.com/i.png[/img][/url]');

        $this->assertStringContainsString('href="https://a.com"', $html);
        $this->assertStringContainsString('src="https://a.com/i.png"', $html);
    }

    public function test_code_placeholder_inside_spoiler_title_is_defused(): void
    {
        $html = (string) Format::formatComment('[spoiler=[code]X[/code]]body[/spoiler]');

        $this->assertStringNotContainsString('codetop', $html);
    }
}
