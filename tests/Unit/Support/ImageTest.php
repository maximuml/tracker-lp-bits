<?php

namespace Tests\Unit\Support;

use App\Support\Image;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
    public function test_weserv_url_with_scheme_only(): void
    {
        $url = Image::weserv('https://example.com/image.jpg');

        $this->assertSame(
            'https://images.weserv.nl/?url=https://example.com/image.jpg&fit=cover',
            $url
        );
    }

    public function test_weserv_url_with_width_and_height(): void
    {
        $url = Image::weserv('http://example.com/pic.png', 120, 80);

        $this->assertSame(
            'http://images.weserv.nl/?url=http://example.com/pic.png&w=120&h=80&fit=cover',
            $url
        );
    }

    public function test_weserv_url_with_custom_fit(): void
    {
        $url = Image::weserv('https://example.com/pic.jpg', 64, 64, 'inside');

        $this->assertSame(
            'https://images.weserv.nl/?url=https://example.com/pic.jpg&w=64&h=64&fit=inside',
            $url
        );
    }

    public function test_weserv_url_preserves_query_string_in_target_url(): void
    {
        $url = Image::weserv('https://example.com/img.php?id=5');

        $this->assertSame(
            'https://images.weserv.nl/?url=https://example.com/img.php?id=5&fit=cover',
            $url
        );
    }
}
