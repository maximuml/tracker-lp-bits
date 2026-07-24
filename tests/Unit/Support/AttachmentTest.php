<?php

namespace Tests\Unit\Support;

use App\Support\Attachment;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
    public function test_render_image_includes_id_and_filename(): void
    {
        $row = [
            'id' => 42,
            'isimage' => 1,
            'filename' => 'cover.jpg',
        ];

        $html = Attachment::render(
            $row,
            'dlkey123',
            true,
            true,
            'https://example.com/cover.jpg',
            '1.23 MB',
            '2026-01-01',
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('id="attach42"', $html);
        $this->assertStringContainsString('alt="cover.jpg"', $html);
        $this->assertStringContainsString('src="https://example.com/cover.jpg"', $html);
        $this->assertStringContainsString('data-zoomable', $html);
        $this->assertStringContainsString('Size', $html);
    }

    public function test_render_image_without_resizer_omits_zoom_attribute(): void
    {
        $row = [
            'id' => 7,
            'isimage' => 1,
            'filename' => 'x.png',
        ];

        $html = Attachment::render(
            $row,
            'dlkey',
            true,
            false,
            '/x.png',
            '100 KB',
            'now',
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringNotContainsString('data-zoomable', $html);
    }

    public function test_render_torrent_file_uses_torrent_icon(): void
    {
        $row = [
            'id' => 3,
            'isimage' => 0,
            'filetype' => 'application/x-bittorrent',
            'filename' => 'file.torrent',
            'downloads' => 10,
        ];

        $html = Attachment::render(
            $row,
            'abc',
            true,
            true,
            '',
            '5 MB',
            '2026-01-02',
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('pic/attachicons/torrent.gif', $html);
        $this->assertStringContainsString('getattachment.php?id=3&amp;dlkey=abc', $html);
        $this->assertStringContainsString('file.torrent', $html);
        $this->assertStringContainsString('5 MB', $html);
    }

    public function test_render_unknown_file_uses_common_icon(): void
    {
        $row = [
            'id' => 1,
            'isimage' => 0,
            'filetype' => 'text/plain',
            'filename' => 'notes.txt',
            'downloads' => 0,
        ];

        $html = Attachment::render(
            $row,
            'key',
            true,
            true,
            '',
            '1 KB',
            'today',
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('pic/attachicons/common.gif', $html);
    }
}
