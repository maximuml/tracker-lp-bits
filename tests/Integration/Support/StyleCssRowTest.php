<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Contracts\Repositories\StyleRepositoryInterface;
use App\Support\Cache\NexusCache;
use App\Support\Style;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Tests for Style::cssRow() cache behaviour — in particular the
 * revalidation of a stale 'stylesheet_content' blob that predates a
 * stylesheets-table write (missing rows were silently falling back to
 * the default stylesheet for the whole ~26h TTL).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class StyleCssRowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Style::resetState();
    }

    protected function tearDown(): void
    {
        Style::resetState();
        parent::tearDown();
    }

    public function test_stale_cached_map_is_revalidated_once_against_db(): void
    {
        // Redis blob predates the Unshatter insert — holds only Classic.
        $cache = $this->fakeCache([
            4 => ['id' => 4, 'uri' => 'styles/Classic/', 'name' => 'Classic'],
        ]);
        $rows = [
            4 => ['id' => 4, 'uri' => 'styles/Classic/', 'name' => 'Classic'],
            5 => ['id' => 5, 'uri' => 'styles/Unshatter/', 'name' => 'Unshatter'],
        ];
        $repo = $this->fakeRepo();
        $repo->shouldReceive('fetchAll')->once()->andReturn($rows);
        app()->instance(StyleRepositoryInterface::class, $repo);

        $row = Style::cssRow($cache, 5, 4);

        $this->assertSame('styles/Unshatter/', $row['uri']);
        // The refreshed map is pushed back into the cache.
        $this->assertCount(2, $cache->stored['stylesheet_content'] ?? []);
    }

    public function test_cached_map_is_served_without_db_hit_when_row_present(): void
    {
        $cache = $this->fakeCache([
            4 => ['id' => 4, 'uri' => 'styles/Classic/', 'name' => 'Classic'],
            5 => ['id' => 5, 'uri' => 'styles/Unshatter/', 'name' => 'Unshatter'],
        ]);
        $repo = $this->fakeRepo();
        $repo->shouldNotReceive('fetchAll');
        app()->instance(StyleRepositoryInterface::class, $repo);

        $row = Style::cssRow($cache, 5, 4);

        $this->assertSame('styles/Unshatter/', $row['uri']);
    }

    public function test_missing_row_falls_back_to_default_after_refresh(): void
    {
        // user.stylesheet points at a stylesheet that no longer exists —
        // refresh confirms the table has no such row, then default wins.
        $cache = $this->fakeCache([
            4 => ['id' => 4, 'uri' => 'styles/Classic/', 'name' => 'Classic'],
        ]);
        $rows = [4 => ['id' => 4, 'uri' => 'styles/Classic/', 'name' => 'Classic']];
        $repo = $this->fakeRepo();
        $repo->shouldReceive('fetchAll')->once()->andReturn($rows);
        app()->instance(StyleRepositoryInterface::class, $repo);

        $row = Style::cssRow($cache, 99, 4);

        $this->assertSame('styles/Classic/', $row['uri']);
    }

    public function test_refresh_runs_only_once_per_process(): void
    {
        $cache = $this->fakeCache([
            4 => ['id' => 4, 'uri' => 'styles/Classic/', 'name' => 'Classic'],
        ]);
        $rows = [
            4 => ['id' => 4, 'uri' => 'styles/Classic/', 'name' => 'Classic'],
            5 => ['id' => 5, 'uri' => 'styles/Unshatter/', 'name' => 'Unshatter'],
        ];
        $repo = $this->fakeRepo();
        $repo->shouldReceive('fetchAll')->once()->andReturn($rows);
        app()->instance(StyleRepositoryInterface::class, $repo);

        Style::cssRow($cache, 5, 4);
        // A second request asking for an id still absent from the map
        // must not pay another fetchAll.
        $row = Style::cssRow($cache, 77, 4);

        $this->assertSame('styles/Classic/', $row['uri']);
    }

    /**
     * Minimal stand-in for NexusCache: cssRow only relies on get()/put().
     */
    private function fakeCache(array $initial): NexusCache
    {
        return new class($initial) extends NexusCache
        {
            /** @var array<string, mixed> */
            public array $stored = [];

            public function __construct(array $initial)
            {
                if ($initial !== []) {
                    $this->stored['stylesheet_content'] = $initial;
                }
            }

            public function get(string $Key): mixed
            {
                return $this->stored[$Key] ?? false;
            }

            public function put(string $Key, mixed $Value, int $Duration = 3600): void
            {
                $this->stored[$Key] = $Value;
            }
        };
    }

    /**
     * @return MockInterface&StyleRepositoryInterface
     */
    private function fakeRepo(): MockInterface
    {
        return Mockery::mock(StyleRepositoryInterface::class);
    }
}
