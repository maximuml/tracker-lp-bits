<?php

namespace Tests\Integration\Support;

use App\Support\Ratio;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class RatioRenderTest extends TestCase
{
    public function test_image_picks_smiley_for_each_bucket(): void
    {
        // Verifies both the bucket boundaries (>=) and the
        // not-quite-monotonic smiley index ordering. The indexes
        // (163 / 117 / 5 / 3 / 2 / 34 / 10 / 52) reference real
        // files under `public/pic/smilies/*.gif`.
        $this->assertSame('<img src="pic/smilies/163.gif" alt="" />', (string) Ratio::image(16));
        $this->assertSame('<img src="pic/smilies/163.gif" alt="" />', (string) Ratio::image(32));
        $this->assertSame('<img src="pic/smilies/117.gif" alt="" />', (string) Ratio::image(8));
        $this->assertSame('<img src="pic/smilies/117.gif" alt="" />', (string) Ratio::image(15.999));
        $this->assertSame('<img src="pic/smilies/5.gif" alt="" />', (string) Ratio::image(4));
        $this->assertSame('<img src="pic/smilies/3.gif" alt="" />', (string) Ratio::image(2));
        $this->assertSame('<img src="pic/smilies/2.gif" alt="" />', (string) Ratio::image(1));
        $this->assertSame('<img src="pic/smilies/34.gif" alt="" />', (string) Ratio::image(0.5));
        $this->assertSame('<img src="pic/smilies/10.gif" alt="" />', (string) Ratio::image(0.25));
        $this->assertSame('<img src="pic/smilies/52.gif" alt="" />', (string) Ratio::image(0));
        $this->assertSame('<img src="pic/smilies/52.gif" alt="" />', (string) Ratio::image(0.24));
    }

    public function test_user_ratio_html_three_decimals_with_color_below_one(): void
    {
        // 500/1000 = 0.5 → color() falls into `< 0.6` bucket → #aa0000.
        $this->assertSame(
            '<span class="nx-ratio-6">0.500</span>',
            Ratio::userRatioHtml(500, 1000, 'tip', 'Infinity'),
        );
    }

    public function test_user_ratio_html_ignores_tooltip_for_legacy_api(): void
    {
        // The legacy `get_ratio()` never emitted a tooltip wrapper, so
        // the tooltip parameter is accepted for caller convenience
        // but not rendered.
        $this->assertSame('---', Ratio::userRatioHtml(0, 0, "it's \"quoted\"", 'Inf'));
        $this->assertSame('Inf', Ratio::userRatioHtml(1024, 0, "it's \"quoted\"", 'Inf'));
        $this->assertSame('<span class="nx-ratio-6">0.500</span>', Ratio::userRatioHtml(500, 1000, "it's \"quoted\"", 'Inf'));
    }

    public function test_user_ratio_html_three_decimals_use_number_format_rounding(): void
    {
        // 1/3 = 0.333... → number_format rounds to 0.333.
        // 2/3 = 0.666... → number_format rounds half-to-even → 0.667.
        // Pinned to document the difference vs `Ratio::share()` which
        // uses floor-truncation. Color buckets: 0.333 falls into
        // `< 0.4` (#cc0000), 0.667 falls into `< 0.7` (#990000).
        $result = Ratio::userRatioHtml(1, 3, 'tip', 'Inf');
        $this->assertSame('<span class="nx-ratio-4">0.333</span>', $result);
        $result = Ratio::userRatioHtml(2, 3, 'tip', 'Inf');
        $this->assertSame('<span class="nx-ratio-7">0.667</span>', $result);
    }
}
