<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CoverThumb;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * CoverThumb::resizeLocal() guards: `..` path segments must never
 * escape the site root, and oversized/invalid image blobs must be
 * rejected before imagecreatefromstring() (decompression bomb).
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class CoverThumbTest extends TestCase
{
    private string $root;

    private string $saveDir;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir().'/coverthumb_'.bin2hex(random_bytes(4));
        $this->root = $base.'/root/';
        $this->saveDir = 'covers_out';
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmTree(dirname($this->root));
        parent::tearDown();
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }

    private static function pngHeader(int $width, int $height): string
    {
        return "\x89PNG\r\n\x1a\n"
            .pack('N', 13).'IHDR'
            .pack('N', $width).pack('N', $height)
            ."\x08\x06\x00\x00\x00"
            ."\x00\x00\x00\x00";
    }

    private function thumb(): CoverThumb
    {
        return new CoverThumb(null);
    }

    public function test_traversal_outside_root_is_rejected(): void
    {
        $outside = dirname($this->root).'/outside.png';
        file_put_contents($outside, self::pngHeader(10, 10));

        $result = $this->thumb()->url('../outside.png', 100, 100, 80, $this->saveDir, 'covers', $this->root);

        $this->assertSame('../outside.png', $result);
    }

    public function test_oversized_image_is_not_decoded(): void
    {
        file_put_contents($this->root.'big.png', self::pngHeader(6000, 6000));

        $result = $this->thumb()->url('big.png', 100, 100, 80, $this->saveDir, 'covers', $this->root);

        $this->assertSame('big.png', $result);
        // No thumbnail was generated anywhere under the save dir.
        $this->assertSame([], glob($this->root.$this->saveDir.'/covers/*/*.jpg') ?: []);
    }

    public function test_invalid_image_is_not_decoded(): void
    {
        file_put_contents($this->root.'fake.png', 'definitely not a png');

        $result = $this->thumb()->url('fake.png', 100, 100, 80, $this->saveDir, 'covers', $this->root);

        $this->assertSame('fake.png', $result);
    }
}
