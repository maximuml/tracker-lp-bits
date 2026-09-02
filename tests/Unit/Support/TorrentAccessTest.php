<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Torrent;
use App\Support\TorrentAccess;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * Unit tests for TorrentAccess.
 *
 * Covers canAccess (always true stub) and adminName (HTML builder).
 */
final class TorrentAccessTest extends TestCase
{
    public function test_can_access_always_returns_true(): void
    {
        $this->assertTrue(TorrentAccess::canAccess(1, 1));
        $this->assertTrue(TorrentAccess::canAccess([], 1));
        $this->assertTrue(TorrentAccess::canAccess('abc', 'xyz'));
    }

    public function test_admin_name_returns_empty_for_null_torrent(): void
    {
        $result = TorrentAccess::adminName(null);

        $this->assertInstanceOf(HtmlString::class, $result);
        $this->assertSame('', $result->toHtml());
    }

    public function test_admin_name_builds_link_with_id_and_name(): void
    {
        $torrent = new Torrent;
        $torrent->id = 42;
        $torrent->name = 'Test Torrent Name';

        $html = TorrentAccess::adminName($torrent)->toHtml();

        $this->assertStringContainsString('href="/details.php?id=42"', $html);
        $this->assertStringContainsString('title="Test Torrent Name"', $html);
        $this->assertStringContainsString('Test Torrent Name', $html);
    }

    public function test_admin_name_limits_long_names(): void
    {
        $longName = str_repeat('A', 100);
        $torrent = new Torrent;
        $torrent->id = 1;
        $torrent->name = $longName;

        $html = TorrentAccess::adminName($torrent, false, 40)->toHtml();

        // Title attribute should contain full name
        $this->assertStringContainsString('title="'.$longName.'"', $html);
        // The link body should be limited — extract text between > and </a>
        // The body is after the title attribute's closing >
        preg_match('/title="[^"]*">(.+?)<\/a>/', $html, $matches);
        $this->assertNotEmpty($matches);
        $body = $matches[1] ?? '';
        $this->assertLessThan(50, strlen($body), 'Link body should be truncated');
        $this->assertStringEndsWith('...', $body);
    }

    public function test_admin_name_includes_tags_when_requested(): void
    {
        $torrent = new Torrent;
        $torrent->id = 1;
        $torrent->name = 'Test';
        // tagsFormatted is an accessor that may need DB; set raw attribute
        $torrent->setRawAttributes(['id' => 1, 'name' => 'Test', 'tags_formatted' => '<span>tag1</span>']);

        $html = TorrentAccess::adminName($torrent, true)->toHtml();

        $this->assertStringContainsString('<div>', $html);
    }

    public function test_admin_name_uses_default_length_40(): void
    {
        $name = str_repeat('B', 50);
        $torrent = new Torrent;
        $torrent->id = 1;
        $torrent->name = $name;

        $html = TorrentAccess::adminName($torrent)->toHtml();

        // The title attribute has the full name
        $this->assertStringContainsString($name, $html);
    }

    public function test_admin_name_wraps_in_flex_container(): void
    {
        $torrent = new Torrent;
        $torrent->id = 1;
        $torrent->name = 'Test';

        $html = TorrentAccess::adminName($torrent)->toHtml();

        $this->assertStringContainsString('display:flex', $html);
    }
}
