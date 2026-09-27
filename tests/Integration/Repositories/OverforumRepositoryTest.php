<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\OverforumRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for OverforumRepository.
 *
 * Covers getOverforums().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OverforumRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private OverforumRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(OverforumRepository::class);
    }

    public function test_get_overforums_returns_array(): void
    {
        DB::table('overforums')->insert(['name' => 'Over 1', 'sort' => 1]);

        $overforums = $this->repository->getOverforums();

        $this->assertIsArray($overforums);
        $this->assertNotEmpty($overforums);
    }
}
