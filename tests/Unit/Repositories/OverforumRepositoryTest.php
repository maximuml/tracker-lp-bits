<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\OverforumRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for OverforumRepository.
 *
 * Covers createOverforum(), getOverforums(), getOverforumRow().
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

    public function test_create_overforum(): void
    {
        $this->repository->createOverforum([
            'name' => 'Test Overforum',
            'sort' => 1,
        ]);

        $this->assertDatabaseHas('overforums', ['name' => 'Test Overforum']);
    }

    public function test_get_overforums_returns_array(): void
    {
        $this->repository->createOverforum(['name' => 'Over 1', 'sort' => 1]);

        $overforums = $this->repository->getOverforums();

        $this->assertIsArray($overforums);
        $this->assertNotEmpty($overforums);
    }

    public function test_get_overforum_row_returns_array(): void
    {
        $this->repository->createOverforum(['name' => 'Row Test', 'sort' => 1]);
        $id = DB::table('overforums')->where('name', 'Row Test')->value('id');

        $row = $this->repository->getOverforumRow((int) $id);

        $this->assertNotNull($row);
        $this->assertSame('Row Test', $row['name']);
    }

    public function test_get_overforum_row_returns_null_for_nonexistent(): void
    {
        $row = $this->repository->getOverforumRow(999999);

        $this->assertNull($row);
    }
}
