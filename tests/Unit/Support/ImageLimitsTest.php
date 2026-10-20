<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ImageLimits;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * GD decompression-bomb guard: a forged PNG IHDR can declare
 * gigapixel dimensions in a few dozen bytes; sizeFromString() must
 * reject it before any imagecreatefromstring() call.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class ImageLimitsTest extends TestCase
{
    /**
     * Minimal PNG: signature + IHDR with the given dimensions.
     * getimagesize() does not verify the chunk CRC.
     */
    private static function pngHeader(int $width, int $height): string
    {
        return "\x89PNG\r\n\x1a\n"
            .pack('N', 13).'IHDR'
            .pack('N', $width).pack('N', $height)
            ."\x08\x06\x00\x00\x00"
            ."\x00\x00\x00\x00";
    }

    public function test_oversized_declared_dimensions_are_rejected(): void
    {
        $this->assertNull(ImageLimits::sizeFromString(self::pngHeader(6000, 6000))); // 36 MP
        $this->assertNull(ImageLimits::sizeFromString(self::pngHeader(100000, 1000))); // 100 MP
        $this->assertNull(ImageLimits::sizeFromString(self::pngHeader(0, 100)));
    }

    public function test_valid_small_image_returns_dimensions(): void
    {
        $size = ImageLimits::sizeFromString(self::pngHeader(320, 200));

        $this->assertNotNull($size);
        $this->assertSame(320, $size[0]);
        $this->assertSame(200, $size[1]);
    }

    public function test_non_image_data_is_rejected(): void
    {
        $this->assertNull(ImageLimits::sizeFromString('not an image at all'));
        $this->assertNull(ImageLimits::sizeFromString(''));
    }
}
