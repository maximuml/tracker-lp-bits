<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Cache;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression tests for stored-XSS fixes: FAQ answers used to pass
 * through strip_tags() (which keeps event-handler attributes and
 * javascript: hrefs) into SafeHtml::fromTrustedHtml(), and the
 * aboutnexus page interpolated basic.SITENAME without escaping.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class FaqSanitizeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // /web/faq caches sanitized categories under {lang}_faq for 15
        // minutes — drop it so rows inserted by the test are visible.
        Cache::forgetWithLocales('faq');
    }

    public function test_faq_page_strips_event_handlers_and_javascript_urls(): void
    {
        $categId = (int) DB::table('faq')->insertGetId([
            'link_id' => 9001,
            'type' => 0,
            'lang_id' => 6,
            'question' => 'XSS test section',
            'answer' => '',
            'flag' => 1,
            'categ' => 0,
            'order' => 9990,
        ]);
        DB::table('faq')->insert([
            'link_id' => 9002,
            'type' => 1,
            'lang_id' => 6,
            'question' => 'XSS test item',
            'answer' => 'safe text <img src="/pic/x.png" onerror="alert(1)"><a href="javascript:alert(1)" onmouseover="alert(2)">link</a><script>alert(3)</script><b>bold</b>',
            'flag' => 1,
            'categ' => 9001,
            'order' => 9991,
        ]);

        $response = $this->get('/web/faq');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('onmouseover', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringNotContainsString('<script>alert(3)</script>', $html);
        // Legit formatting survives.
        $this->assertStringContainsString('safe text', $html);
        $this->assertStringContainsString('<b>bold</b>', $html);
    }

    public function test_aboutnexus_escapes_sitename_in_version_note(): void
    {
        Settings::saveBatch('basic', ['SITENAME' => '<img src=x onerror=alert(1)>']);

        $response = $this->get('/web/aboutnexus');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }
}
