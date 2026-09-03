<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\StyleRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for StyleRepository.
 *
 * Covers all(), row(), uri(), highlightColor(), and firstId().
 * The static $rows cache is reset between tests via reflection.
 */
final class StyleRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private StyleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('stylesheets')->delete();
        $this->resetStaticCache();
        $this->repository = new StyleRepository;
    }

    protected function tearDown(): void
    {
        $this->resetStaticCache();
        parent::tearDown();
    }

    public function test_all_returns_empty_array_when_no_stylesheets(): void
    {
        $this->assertSame([], $this->repository->all());
    }

    public function test_all_returns_rows_keyed_by_id(): void
    {
        $this->insertStylesheet(1, 'styles/First/', 'First');
        $this->insertStylesheet(2, 'styles/Second/', 'Second');

        $result = $this->repository->all();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame('First', $result[1]['name']);
        $this->assertSame('Second', $result[2]['name']);
    }

    public function test_all_caches_results_across_calls(): void
    {
        $this->insertStylesheet(1, 'styles/Cached/', 'Cached');

        $first = $this->repository->all();

        // Insert another row after the first call — cache should prevent
        // the new row from appearing on the second call.
        $this->insertStylesheet(2, 'styles/Extra/', 'Extra');

        $second = $this->repository->all();

        $this->assertSame($first, $second);
        $this->assertCount(1, $second);
    }

    public function test_row_returns_null_when_not_found(): void
    {
        $this->insertStylesheet(1, 'styles/One/', 'One');

        $this->assertNull($this->repository->row(999));
    }

    public function test_row_returns_array_when_found(): void
    {
        $this->insertStylesheet(5, 'styles/Five/', 'Five');

        $result = $this->repository->row(5);

        $this->assertNotNull($result);
        $this->assertSame(5, (int) $result['id']);
        $this->assertSame('Five', $result['name']);
    }

    public function test_row_accepts_string_id(): void
    {
        $this->insertStylesheet(7, 'styles/Seven/', 'Seven');

        $result = $this->repository->row('7');

        $this->assertNotNull($result);
        $this->assertSame(7, (int) $result['id']);
    }

    public function test_uri_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->uri(999));
    }

    public function test_uri_returns_uri_when_found(): void
    {
        $this->insertStylesheet(3, 'styles/Three/', 'Three');

        $this->assertSame('styles/Three/', $this->repository->uri(3));
    }

    public function test_highlight_color_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->highlightColor(999));
    }

    public function test_highlight_color_returns_null_when_no_hltr_column(): void
    {
        $this->insertStylesheet(1, 'styles/NoHltr/', 'NoHltr');

        // The stylesheets table has no hltr column, so highlightColor
        // always returns null for existing rows.
        $this->assertNull($this->repository->highlightColor(1));
    }

    public function test_first_id_returns_null_when_empty(): void
    {
        $this->assertNull($this->repository->firstId());
    }

    public function test_first_id_returns_first_id_when_not_empty(): void
    {
        $this->insertStylesheet(10, 'styles/Ten/', 'Ten');
        $this->insertStylesheet(2, 'styles/Two/', 'Two');

        // all() orders by id, so the first key is 2.
        $this->assertSame(2, $this->repository->firstId());
    }

    private function insertStylesheet(int $id, string $uri, string $name): void
    {
        DB::table('stylesheets')->insert([
            'id' => $id,
            'uri' => $uri,
            'name' => $name,
            'addicode' => '',
            'designer' => 'Tester',
            'comment' => '',
        ]);
    }

    private function resetStaticCache(): void
    {
        $property = new \ReflectionProperty(StyleRepository::class, 'rows');
        $property->setValue(null, null);
    }
}
