<?php

namespace Tests\Integration\Support;

use App\Support\Html;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class HtmlRenderTest extends TestCase
{
    public function test_message_alert_with_url_wraps_text_in_anchor(): void
    {
        $this->assertSame(
            '<table class="msg-alert" data-nx="layout"><tr><td class="msg-alert-red">'."\n"
            .'<b><a href="https://example.com/notice" target=\'_blank\'><span class="text-white">Important notice</span></a></b></td></tr></table><br />',
            Html::messageAlert('https://example.com/notice', 'Important notice'),
        );
    }

    public function test_message_alert_with_empty_url_omits_anchor(): void
    {
        $this->assertSame(
            '<table class="msg-alert" data-nx="layout"><tr><td class="msg-alert-red">'."\n"
            .'<b><span class="text-white">Plain alert</span></b></td></tr></table><br />',
            Html::messageAlert('', 'Plain alert'),
        );
    }

    public function test_message_alert_custom_background_color(): void
    {
        // Used by `Sysop\AnnouncementResource` (orange) and the
        // legacy "important" stream — bgcolor maps to a whitelisted
        // msg-alert-* class (inline styles are blocked by CSP).
        $output = Html::messageAlert('', 'Maintenance window', 'orange');

        $this->assertStringContainsString('msg-alert-orange', $output);
    }

    public function test_message_alert_default_color_is_red(): void
    {
        $output = Html::messageAlert('', 'Bad news');

        $this->assertStringContainsString('msg-alert-red', $output);
    }

    public function test_message_alert_escapes_url_but_preserves_text_html(): void
    {
        // Security hardening: the URL is htmlspecialchars'd to prevent
        // href attribute injection, while the text remains raw HTML
        // (authored in the sysop panel, may contain <br/>, <b>, etc.).
        $output = Html::messageAlert(
            'https://example.com/?a=1&b=2',
            'Read <b>this</b> & that',
        );

        $this->assertStringContainsString('href="https://example.com/?a=1&amp;b=2"', $output);
        $this->assertStringContainsString('<span class="text-white">Read <b>this</b> & that</span>', $output);
    }

    public function test_message_alert_uses_target_blank_with_single_quotes(): void
    {
        // The `target='_blank'` uses SINGLE quotes in the legacy
        // output (the surrounding <a> uses double quotes for href).
        // Pinned because `app/Filament/AnnouncementResource` parses
        // these strings with regex to populate the admin-side preview
        // and the quote style matters there.
        $output = Html::messageAlert('https://example.com/x', 'X');

        $this->assertStringContainsString("target='_blank'", $output);
    }
}
