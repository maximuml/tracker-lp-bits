<?php

namespace Tests\Unit\Support;

use App\Support\Progress;
use PHPUnit\Framework\TestCase;

final class ProgressTest extends TestCase
{
    public function test_zero_percent_emits_rest_bar(): void
    {
        $html = Progress::percentImage(0);

        $this->assertStringContainsString('progbarrest', $html);
        $this->assertStringContainsString('style="width: 45px;"', $html);
        $this->assertStringNotContainsString('progbargreen', $html);
        $this->assertStringNotContainsString('progbarred', $html);
    }

    public function test_one_hundred_percent_emits_green_bar(): void
    {
        $html = Progress::percentImage(100);

        $this->assertStringContainsString('progbargreen', $html);
        $this->assertStringContainsString('style="width: 45px;"', $html);
        $this->assertStringNotContainsString('progbarrest', $html);
    }

    public function test_low_percent_uses_red_bar(): void
    {
        $html = Progress::percentImage(25);

        $this->assertStringContainsString('progbarred', $html);
        $this->assertStringContainsString('style="width: 11.25px;"', $html);
        $this->assertStringContainsString('progbarrest', $html);
        $this->assertStringContainsString('style="width: 33.75px;"', $html);
    }

    public function test_mid_percent_uses_yellow_bar(): void
    {
        $html = Progress::percentImage(50);

        $this->assertStringContainsString('progbaryellow', $html);
        $this->assertStringContainsString('style="width: 22.5px;"', $html);
    }

    public function test_high_percent_uses_green_bar(): void
    {
        $html = Progress::percentImage(80);

        $this->assertStringContainsString('progbargreen', $html);
        $this->assertStringContainsString('style="width: 36px;"', $html);
        $this->assertStringContainsString('style="width: 9px;"', $html);
    }

    public function test_accepts_numeric_string(): void
    {
        $html = Progress::percentImage('33');

        $this->assertStringContainsString('progbaryellow', $html);
    }

    public function test_out_of_range_values_emit_empty_progress(): void
    {
        $html = Progress::percentImage(-10);

        $this->assertSame('<img class="bar_left" src="pic/trans.gif" alt="" /><img class="bar_right" src="pic/trans.gif" alt="" />', $html);

        $html = Progress::percentImage(200);
        $this->assertSame('<img class="bar_left" src="pic/trans.gif" alt="" /><img class="bar_right" src="pic/trans.gif" alt="" />', $html);
    }
}
