<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\TorrentPosState;
use App\Exceptions\NexusException;
use App\Models\Category;
use App\Repositories\UploadRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Unit tests for UploadRepository.
 *
 * Covers getCover(), getPrice(), getHitAndRun(), getPosStateInfo().
 */
final class UploadRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UploadRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UploadRepository;
    }

    public function test_get_cover_returns_empty_string_when_no_description(): void
    {
        $request = Request::create('/upload', 'POST', ['descr' => '']);

        $cover = $this->repository->getCover($request);

        $this->assertSame('', $cover);
    }

    public function test_get_cover_returns_empty_string_when_no_image_in_description(): void
    {
        $request = Request::create('/upload', 'POST', ['descr' => 'Just text description without images']);

        $cover = $this->repository->getCover($request);

        $this->assertSame('', $cover);
    }

    public function test_get_cover_returns_first_image_url_from_description(): void
    {
        $descr = '[img]https://example.com/cover1.jpg[/img][img]https://example.com/cover2.jpg[/img]';
        $request = Request::create('/upload', 'POST', ['descr' => $descr]);

        $cover = $this->repository->getCover($request);

        $this->assertSame('https://example.com/cover1.jpg', $cover);
    }

    public function test_get_price_returns_zero_when_no_price_set(): void
    {
        $request = Request::create('/upload', 'POST', []);

        $price = $this->repository->getPrice($request);

        $this->assertSame(0, $price);
    }

    public function test_get_price_returns_zero_when_price_is_zero(): void
    {
        $request = Request::create('/upload', 'POST', ['price' => 0]);

        $price = $this->repository->getPrice($request);

        $this->assertSame(0, $price);
    }

    public function test_get_price_throws_for_non_numeric_price(): void
    {
        $request = Request::create('/upload', 'POST', ['price' => 'abc']);

        $this->expectException(NexusException::class);

        $this->repository->getPrice($request);
    }

    public function test_get_hit_and_run_returns_zero_by_default(): void
    {
        $category = Category::factory()->create();
        $request = Request::create('/upload', 'POST', []);

        $hr = $this->repository->getHitAndRun($request, $category);

        $this->assertSame(0, $hr);
    }

    public function test_get_hit_and_run_throws_for_invalid_value(): void
    {
        $category = Category::factory()->create();
        $request = Request::create('/upload', 'POST', ['hr' => 5]);

        $this->expectException(NexusException::class);

        $this->repository->getHitAndRun($request, $category);
    }

    public function test_get_pos_state_info_returns_none_by_default(): void
    {
        $request = Request::create('/upload', 'POST', []);

        $result = $this->repository->getPosStateInfo($request);

        $this->assertSame(TorrentPosState::NONE->value, $result['posState']);
        $this->assertNull($result['posStateUntil']);
    }

    public function test_get_pos_state_info_returns_none_with_null_until_when_set_to_none(): void
    {
        $request = Request::create('/upload', 'POST', [
            'pos_state' => TorrentPosState::NONE->value,
            'pos_state_until' => '2025-01-01',
        ]);

        $result = $this->repository->getPosStateInfo($request);

        $this->assertSame(TorrentPosState::NONE->value, $result['posState']);
        $this->assertNull($result['posStateUntil']);
    }
}
