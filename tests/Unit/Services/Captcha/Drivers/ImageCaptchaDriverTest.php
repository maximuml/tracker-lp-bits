<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Captcha\Drivers;

use App\Models\RegImage;
use App\Repositories\RegImageRepository;
use App\Services\Captcha\Drivers\ImageCaptchaDriver;
use App\Support\HeaderBag;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pins the HeaderBag contract on the captcha driver: a successful render
 * must set Content-Type: image/png, the fallback path must set status 404.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ImageCaptchaDriverTest extends TestCase
{
    private HeaderBag $headerBag;

    private ImageCaptchaDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();

        $this->headerBag = new HeaderBag;
        $this->driver = new ImageCaptchaDriver([], $this->headerBag, new RegImageRepository);
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    public function test_is_enabled(): void
    {
        $this->assertTrue($this->driver->isEnabled());
    }

    public function test_issue_inserts_regimage_row(): void
    {
        $imagehash = $this->driver->issue();

        $this->assertNotSame('', $imagehash);
        $this->assertDatabaseHas('regimages', ['imagehash' => $imagehash]);
    }

    public function test_output_image_writes_png_content_type_header(): void
    {
        if (! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('GD extension required');
        }

        RegImage::query()->create([
            'imagehash' => 'testhash',
            'imagestring' => '123456',
            'dateline' => time(),
        ]);

        $bytes = $this->driver->imageBytes('testhash');

        $this->assertNotSame('', $bytes);
        $this->assertSame('image/png', $this->headerBag->first('Content-Type'));
    }

    public function test_output_image_missing_hash_sets_404_status(): void
    {
        $bytes = $this->driver->imageBytes('does-not-exist');

        $this->assertSame('', $bytes);
        $this->assertSame(404, $this->headerBag->getStatusCode());
    }
}
