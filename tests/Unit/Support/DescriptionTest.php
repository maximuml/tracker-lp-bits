<?php

namespace Tests\Unit\Support;

use App\Support\Description;
use PHPUnit\Framework\TestCase;

final class DescriptionTest extends TestCase
{
    public function test_image_urls_empty_array_returns_empty_list(): void
    {
        $this->assertSame([], Description::imageUrls([]));
    }

    public function test_image_urls_collects_image_and_attachment_in_order(): void
    {
        $arr = [
            ['type' => 'text', 'data' => ['value' => 'hello']],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/a.png']],
            ['type' => 'attachment', 'data' => ['url' => 'https://example.com/b.jpg']],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/c.gif']],
        ];

        $this->assertSame(
            [
                'https://example.com/a.png',
                'https://example.com/b.jpg',
                'https://example.com/c.gif',
            ],
            Description::imageUrls($arr),
        );
    }

    public function test_image_urls_skips_non_image_types(): void
    {
        $arr = [
            ['type' => 'text', 'data' => ['url' => 'https://example.com/text.png']],
            ['type' => 'quote', 'data' => ['url' => 'https://example.com/quote.png']],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/img.png']],
        ];

        $this->assertSame(['https://example.com/img.png'], Description::imageUrls($arr));
    }

    public function test_image_urls_skips_nodes_with_empty_url(): void
    {
        $arr = [
            ['type' => 'image', 'data' => ['url' => '']],
            ['type' => 'image', 'data' => []],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/keep.png']],
        ];

        $this->assertSame(['https://example.com/keep.png'], Description::imageUrls($arr));
    }

    public function test_image_urls_skips_literal_zero_string_url(): void
    {
        $arr = [
            ['type' => 'image', 'data' => ['url' => '0']],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/real.png']],
        ];

        $this->assertSame(['https://example.com/real.png'], Description::imageUrls($arr));
    }

    public function test_image_urls_skips_malformed_nodes_gracefully(): void
    {
        $arr = [
            'not-an-array',
            42,
            null,
            [],
            ['type' => 'image'],
            ['type' => 42, 'data' => ['url' => 'https://example.com/a.png']],
            ['data' => ['url' => 'https://example.com/typeless.png']],
            ['type' => 'image', 'data' => 'not-an-array'],
            ['type' => 'image', 'data' => ['url' => 12345]],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/ok.png']],
        ];

        $this->assertSame(['https://example.com/ok.png'], Description::imageUrls($arr));
    }

    public function test_first_image_url_empty_array_returns_default(): void
    {
        $this->assertSame('', Description::firstImageUrl([]));
        $this->assertSame('default.png', Description::firstImageUrl([], 'default.png'));
    }

    public function test_first_image_url_returns_first_match_and_stops(): void
    {
        $arr = [
            ['type' => 'text', 'data' => ['url' => 'https://example.com/skip.png']],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/first.png']],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/second.png']],
        ];

        $this->assertSame('https://example.com/first.png', Description::firstImageUrl($arr));
    }

    public function test_first_image_url_accepts_attachment_node(): void
    {
        $arr = [
            ['type' => 'attachment', 'data' => ['url' => 'https://example.com/file.zip']],
        ];

        $this->assertSame('https://example.com/file.zip', Description::firstImageUrl($arr));
    }

    public function test_first_image_url_returns_default_when_no_match(): void
    {
        $arr = [
            ['type' => 'text', 'data' => ['value' => 'hi']],
            ['type' => 'quote', 'data' => ['value' => 'quote']],
        ];

        $this->assertSame(
            'https://example.com/nophoto.gif',
            Description::firstImageUrl($arr, 'https://example.com/nophoto.gif'),
        );
    }

    public function test_first_image_url_skips_empty_urls_until_match(): void
    {
        $arr = [
            ['type' => 'image', 'data' => ['url' => '']],
            ['type' => 'image', 'data' => ['url' => '0']],
            ['type' => 'image', 'data' => []],
            ['type' => 'image', 'data' => ['url' => 'https://example.com/finally.png']],
        ];

        $this->assertSame(
            'https://example.com/finally.png',
            Description::firstImageUrl($arr, 'fallback'),
        );
    }

    public function test_first_image_url_returns_default_when_all_malformed(): void
    {
        $arr = [
            'not-an-array',
            ['type' => 'image'],
            ['type' => 'image', 'data' => 'not-an-array'],
            ['type' => 'image', 'data' => ['url' => 999]],
        ];

        $this->assertSame('fallback', Description::firstImageUrl($arr, 'fallback'));
    }
}
