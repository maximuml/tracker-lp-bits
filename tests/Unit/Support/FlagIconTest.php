<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\FlagIcon;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class FlagIconTest extends TestCase
{
    public function test_mapped_basename_returns_svg(): void
    {
        $this->assertSame('pic/flags/us.svg', FlagIcon::path('usa.gif'));
        $this->assertSame('pic/flags/gb.svg', FlagIcon::path('uk.gif'));
        $this->assertSame('pic/flags/cz.svg', FlagIcon::path('czechrep.gif'));
        $this->assertSame('pic/flags/se.svg', FlagIcon::path('sweden.gif'));
    }

    public function test_unmapped_basename_falls_back_to_gif(): void
    {
        $this->assertSame('pic/flag/yugoslavia.gif', FlagIcon::path('yugoslavia.gif'));
        $this->assertSame('pic/flag/jollyroger.gif', FlagIcon::path('jollyroger.gif'));
        $this->assertSame('pic/flag/ussr.gif', FlagIcon::path('ussr.gif'));
        $this->assertSame('pic/flag/custom.png', FlagIcon::path('custom.png'));
    }

    public function test_empty_and_null_fall_back_to_gif_dir(): void
    {
        $this->assertSame('pic/flag/', FlagIcon::path(''));
        $this->assertSame('pic/flag/', FlagIcon::path(null));
    }

    public function test_extension_and_case_are_ignored(): void
    {
        $this->assertSame('pic/flags/us.svg', FlagIcon::path('USA.GIF'));
        $this->assertSame('pic/flags/us.svg', FlagIcon::path('usa'));
    }

    public function test_every_mapped_svg_exists_on_disk(): void
    {
        $ref = new \ReflectionClass(FlagIcon::class);
        /** @var array<string, string> $map */
        $map = $ref->getConstant('FLAGPIC_TO_ISO');

        $missing = [];
        foreach (array_unique($map) as $iso) {
            $file = __DIR__.'/../../../public/pic/flags/'.$iso.'.svg';
            if (! is_file($file)) {
                $missing[] = $iso;
            }
        }

        $this->assertSame([], $missing, 'SVG files missing for mapped ISO codes: '.implode(', ', $missing));
    }
}
