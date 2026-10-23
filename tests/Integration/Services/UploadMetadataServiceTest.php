<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Exceptions\UploadValidationException;
use App\Models\Category;
use App\Services\UploadMetadataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pins UploadMetadataService request readers — price, hit-and-run,
 * cover extraction and approval-status defaults.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UploadMetadataServiceTest extends TestCase
{
    use DatabaseTransactions;

    private UploadMetadataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UploadMetadataService::class);
    }

    public function test_get_price_defaults_to_zero(): void
    {
        $request = Request::create('/takeupload', 'POST');

        $this->assertSame(0, $this->service->getPrice($request));
    }

    public function test_get_price_rejects_non_numeric(): void
    {
        $request = Request::create('/takeupload', 'POST', ['price' => 'abc']);

        try {
            $this->service->getPrice($request);
            $this->fail('expected UploadValidationException');
        } catch (UploadValidationException $e) {
            $this->assertSame('price', $e->field());
        }
    }

    public function test_get_hit_and_run_defaults_to_zero(): void
    {
        /** @var Category $category */
        $category = Category::query()->first() ?? new Category(['mode' => 0]);
        $request = Request::create('/takeupload', 'POST');

        $this->assertSame(0, $this->service->getHitAndRun($request, $category));
    }

    public function test_get_hit_and_run_rejects_invalid_value(): void
    {
        /** @var Category $category */
        $category = Category::query()->first() ?? new Category(['mode' => 0]);
        $request = Request::create('/takeupload', 'POST', ['hr' => 7]);

        try {
            $this->service->getHitAndRun($request, $category);
            $this->fail('expected UploadValidationException');
        } catch (UploadValidationException $e) {
            $this->assertSame('hr', $e->field());
        }
    }

    public function test_get_cover_empty_when_no_description(): void
    {
        $request = Request::create('/takeupload', 'POST');

        $this->assertSame('', $this->service->getCover($request));
    }

    public function test_get_approval_status_defaults_to_none_for_guest(): void
    {
        $request = Request::create('/takeupload', 'POST');

        $this->assertSame(0, $this->service->getApprovalStatus($request));
    }
}
