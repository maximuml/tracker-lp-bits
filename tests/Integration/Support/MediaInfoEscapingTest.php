<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Torrent\BdInfoExtra;
use App\Support\Torrent\TechnicalInformation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Stored-XSS regression tests for raw MediaInfo / BDInfo dumps on the
 * torrent details page.
 *
 * Both classes wrap the uploader-supplied text in literal markup
 * (`[spoiler]<pre>…</pre>[/spoiler]`) and render it via
 * `Format::formatComment($x, stripHtml: false)` — the mode is required
 * for the structural tags to survive, so the user text itself must be
 * escaped before concatenation. A stored `</pre><script>` payload must
 * never reach the page as markup.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class MediaInfoEscapingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_media_info_raw_dump_escapes_html_payload(): void
    {
        $html = (new TechnicalInformation('</pre><script>alert(1)</script><pre>'))->renderOnDetailsPage()->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_bd_info_raw_dump_escapes_html_payload(): void
    {
        $html = (new BdInfoExtra('</pre><script>alert(1)</script><pre>'))->renderOnDetailsPage()->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
