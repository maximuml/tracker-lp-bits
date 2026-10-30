<?php

namespace Tests\Integration\Services;

use App\Services\AttachmentRenderService;
use App\Support\Html\SafeHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class AttachmentRenderServiceTest extends TestCase
{
    public function test_render_image_includes_id_and_filename(): void
    {
        $row = [
            'id' => 42,
            'isimage' => 1,
            'filename' => 'cover.jpg',
        ];

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'dlkey123',
            true,
            true,
            'https://example.com/cover.jpg',
            '1.23 MB',
            SafeHtml::fromPlainText('2026-01-01'),
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

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'dlkey',
            true,
            false,
            '/x.png',
            '100 KB',
            SafeHtml::fromPlainText('now'),
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

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'abc',
            true,
            true,
            '',
            '5 MB',
            SafeHtml::fromPlainText('2026-01-02'),
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('pic/attachicons/torrent.gif', $html);
        $this->assertStringContainsString('/web/getattachment?id=3&amp;dlkey=abc', $html);
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

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'key',
            true,
            true,
            '',
            '1 KB',
            SafeHtml::fromPlainText('today'),
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('pic/attachicons/common.gif', $html);
    }

    #[DataProvider('fileIconCases')]
    public function test_icon_matches_filetype(string $filetype, string $icon): void
    {
        $row = [
            'id' => 5,
            'isimage' => 0,
            'filetype' => $filetype,
            'filename' => 'asset.bin',
            'downloads' => 2,
        ];

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'k',
            true,
            true,
            '',
            '2 KB',
            SafeHtml::fromPlainText('then'),
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString("pic/attachicons/$icon.gif", $html);
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function fileIconCases(): iterable
    {
        yield 'zip' => ['application/zip', 'archive'];
        yield 'rar' => ['application/rar', 'archive'];
        yield '7z' => ['application/x-7z-compressed', 'archive'];
        yield 'gzip' => ['application/x-gzip', 'archive'];
        yield 'mpeg' => ['audio/mpeg', 'audio'];
        yield 'ogg' => ['audio/ogg', 'audio'];
        yield 'flv' => ['video/x-flv', 'flv'];
    }

    public function test_render_file_shows_downloads_label_and_count(): void
    {
        $row = [
            'id' => 9,
            'isimage' => 0,
            'filetype' => 'application/zip',
            'filename' => 'pack.zip',
            'downloads' => 1234,
        ];

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'key',
            true,
            true,
            '',
            '9 MB',
            SafeHtml::fromPlainText('2026-02-02'),
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('Downloads', $html);
        $this->assertStringContainsString('1,234', $html);
    }

    public function test_render_missing_isimage_renders_file_not_image(): void
    {
        $row = [
            'id' => 11,
            'filetype' => 'text/plain',
            'filename' => 'noimage.txt',
            'downloads' => 3,
        ];

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'key',
            true,
            true,
            '',
            '1 KB',
            SafeHtml::fromPlainText('now'),
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('/web/getattachment?id=11&amp;dlkey=key', $html);
        $this->assertStringNotContainsString('data-zoomable', $html);
    }

    public function test_render_image_flag_off_renders_file(): void
    {
        $row = [
            'id' => 12,
            'isimage' => 1,
            'filetype' => 'image/png',
            'filename' => 'p.png',
            'downloads' => 4,
        ];

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'key',
            false,
            true,
            '/p.png',
            '50 KB',
            SafeHtml::fromPlainText('now'),
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('/web/getattachment?id=12&amp;dlkey=key', $html);
    }

    public function test_render_missing_id_and_downloads_fall_back_to_zero(): void
    {
        $row = [
            'isimage' => 0,
            'filetype' => 'text/plain',
            'filename' => 'empty.txt',
        ];

        $html = $this->app->make(AttachmentRenderService::class)->render(
            $row,
            'key',
            true,
            true,
            '',
            '0 B',
            SafeHtml::fromPlainText('now'),
            ['size' => 'Size', 'downloads' => 'Downloads'],
        );

        $this->assertStringContainsString('/web/getattachment?id=0&amp;dlkey=key', $html);
        $this->assertStringNotContainsString('-1', $html);
    }
}
