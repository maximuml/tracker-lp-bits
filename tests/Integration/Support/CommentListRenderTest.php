<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Format;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * End-to-end coverage for BBCode list blocks through
 * Comment::format (via Format::formatComment) — verifies lists()
 * runs before nl2br so nested BBCode still renders inside items.
 * Lives in Integration because formatComment reads SiteConfig
 * (settings table), which the no-services Unit job cannot reach.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class CommentListRenderTest extends TestCase
{
    public function test_format_comment_renders_list_block_end_to_end(): void
    {
        $html = (string) Format::formatComment("[list]\n[*] alpha\n[*] beta\n[/list]");

        $this->assertStringContainsString('<ul><li>alpha', $html);
        $this->assertStringContainsString('</li><li>beta</li></ul>', $html);
        $this->assertStringNotContainsString('[list]', $html);
        $this->assertStringNotContainsString('[*]', $html);
    }

    public function test_format_comment_renders_ordered_and_nested_bbcode(): void
    {
        $html = (string) Format::formatComment("[ol]\n[*] [b]one[/b]\n[*] [url=https://example.com]two[/url]\n[/ol]");

        $this->assertStringContainsString('<ol><li><b>one</b>', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringNotContainsString('[ol]', $html);
        $this->assertStringNotContainsString('[*]', $html);
    }

    public function test_format_comment_preserves_unmatched_list_block_verbatim(): void
    {
        $html = (string) Format::formatComment('[list] alpha');

        $this->assertStringContainsString('[list]', $html);
    }
}
