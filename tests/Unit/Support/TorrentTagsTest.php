<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TorrentTags;
use Tests\TestCase;

/**
 * Unit tests for TorrentTags::render().
 *
 * The render method is a pure function that builds HTML for tag
 * checkboxes or tag spans based on a bitmask. No DI, no DB, no cache.
 */
final class TorrentTagsTest extends TestCase
{
    /** @return array<string, string> */
    private function labels(): array
    {
        return [
            'text_tag_no_release_to_any_other' => 'No Release',
            'text_tag_first_release' => 'First Release',
            'text_tag_official' => 'Official',
            'text_tag_diy' => 'DIY',
            'text_tag_mother_language' => 'Mother Language',
            'text_tag_mother_language_subtitle' => 'Mother Language Subtitle',
            'text_tag_hdr' => 'HDR',
        ];
    }

    public function test_render_checkbox_with_no_tags(): void
    {
        $html = TorrentTags::render(0, 'checkbox', $this->labels());

        // 7 checkboxes, none checked
        $this->assertSame(7, substr_count($html, '<input type="checkbox"'));
        $this->assertSame(0, substr_count($html, 'checked'));
    }

    public function test_render_checkbox_with_all_tags(): void
    {
        // All 7 bits set: 2^0 + 2^1 + ... + 2^6 = 127
        $html = TorrentTags::render(127, 'checkbox', $this->labels());

        $this->assertSame(7, substr_count($html, 'checked'));
    }

    public function test_render_checkbox_with_single_tag(): void
    {
        // Only bit 0 (No Release) set: value = 1
        $html = TorrentTags::render(1, 'checkbox', $this->labels());

        $this->assertSame(1, substr_count($html, 'checked'));
        $this->assertStringContainsString('No Release', $html);
    }

    public function test_render_checkbox_with_multiple_tags(): void
    {
        // Bits 0, 2, 4 set: 1 + 4 + 16 = 21
        $html = TorrentTags::render(21, 'checkbox', $this->labels());

        $this->assertSame(3, substr_count($html, 'checked'));
        $this->assertStringContainsString('No Release', $html);
        $this->assertStringContainsString('Official', $html);
        $this->assertStringContainsString('Mother Language', $html);
    }

    public function test_render_span_with_no_tags(): void
    {
        $html = TorrentTags::render(0, 'span', $this->labels());

        // No spans for 0 tags
        $this->assertSame('', $html);
    }

    public function test_render_span_with_all_tags(): void
    {
        $html = TorrentTags::render(127, 'span', $this->labels());

        // 7 spans
        $this->assertSame(7, substr_count($html, '<span'));
    }

    public function test_render_span_with_single_tag(): void
    {
        $html = TorrentTags::render(2, 'span', $this->labels());

        // Only bit 1 (First Release) set
        $this->assertSame(1, substr_count($html, '<span'));
        $this->assertStringContainsString('First Release', $html);
        $this->assertStringContainsString('#8F77B5', $html);
    }

    public function test_render_span_includes_color_style(): void
    {
        $html = TorrentTags::render(1, 'span', $this->labels());

        $this->assertStringContainsString('background-color:#ff0000', $html);
        $this->assertStringContainsString('color:white', $html);
        $this->assertStringContainsString('border-radius:15%', $html);
    }

    public function test_render_checkbox_uses_value_powers_of_two(): void
    {
        $html = TorrentTags::render(0, 'checkbox', $this->labels());

        // Values should be 1, 2, 4, 8, 16, 32, 64
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('value="2"', $html);
        $this->assertStringContainsString('value="4"', $html);
        $this->assertStringContainsString('value="8"', $html);
        $this->assertStringContainsString('value="16"', $html);
        $this->assertStringContainsString('value="32"', $html);
        $this->assertStringContainsString('value="64"', $html);
    }

    public function test_render_with_empty_labels(): void
    {
        $html = TorrentTags::render(127, 'checkbox', []);

        // Should still produce 7 checkboxes, all checked, with empty text
        $this->assertSame(7, substr_count($html, '<input type="checkbox"'));
        $this->assertSame(7, substr_count($html, 'checked'));
    }

    public function test_render_accepts_string_tags_value(): void
    {
        $html = TorrentTags::render('3', 'checkbox', $this->labels());

        // Bits 0 and 1 set
        $this->assertSame(2, substr_count($html, 'checked'));
    }

    public function test_render_uses_default_type_checkbox(): void
    {
        $html = TorrentTags::render(0, 'checkbox', $this->labels());

        $this->assertStringContainsString('type="checkbox"', $html);
    }

    public function test_render_span_with_hdr_tag_has_correct_color(): void
    {
        // Bit 6 = HDR, value = 64
        $html = TorrentTags::render(64, 'span', $this->labels());

        $this->assertStringContainsString('#38b03f', $html);
        $this->assertStringContainsString('HDR', $html);
    }
}
