<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\SettingRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for SettingRepository.
 *
 * Covers getAll(), getByName(), getList(), store() and saveBatch().
 *
 * Uses unique test-prefixed setting names (test_*) to avoid polluting
 * the Setting::get() function-level static cache that other tests depend on.
 */
final class SettingRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private SettingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('settings')->where('name', 'like', 'test_%')->delete();
        $this->repository = new SettingRepository;
    }

    public function test_get_all_returns_autoload_settings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'test_basic.SITENAME', 'value' => 'MySite', 'autoload' => 1],
            ['name' => 'test_hidden.key', 'value' => 'secret', 'autoload' => 0],
        ]);

        $result = $this->repository->getAll();

        $this->assertSame('MySite', $result['test_basic']['SITENAME']);
        $this->assertArrayNotHasKey('test_hidden', $result);
    }

    public function test_get_by_name_returns_default_when_not_found(): void
    {
        $this->assertNull($this->repository->getByName('test_nonexistent'));
        $this->assertSame('fallback', $this->repository->getByName('test_nonexistent', 'fallback'));
    }

    public function test_get_by_name_returns_value_when_found(): void
    {
        DB::table('settings')->insert([
            ['name' => 'test_basic.SITENAME', 'value' => 'MySite', 'autoload' => 1],
        ]);

        $this->assertSame('MySite', $this->repository->getByName('test_basic.SITENAME'));
    }

    public function test_get_list_without_prefix_returns_all(): void
    {
        DB::table('settings')->insert([
            ['name' => 'test_basic.SITENAME', 'value' => 'MySite', 'autoload' => 1],
        ]);

        $result = $this->repository->getList([]);

        $this->assertSame('MySite', $result['test_basic']['SITENAME']);
    }

    public function test_get_list_with_prefix_returns_subset(): void
    {
        DB::table('settings')->insert([
            ['name' => 'test_basic.SITENAME', 'value' => 'MySite', 'autoload' => 1],
            ['name' => 'test_other.key', 'value' => 'val', 'autoload' => 1],
        ]);

        $result = $this->repository->getList(['prefix' => 'test_basic']);

        $this->assertArrayHasKey('test_basic', $result);
        $this->assertSame('MySite', $result['test_basic']['SITENAME']);
        $this->assertArrayNotHasKey('test_other', $result);
    }

    public function test_get_list_with_prefix_returns_empty_when_not_found(): void
    {
        $result = $this->repository->getList(['prefix' => 'test_nonexistent']);

        $this->assertSame(['test_nonexistent' => []], $result);
    }

    public function test_store_throws_when_value_is_not_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->store(['test_basic' => 'not-an-array']);
    }

    public function test_store_returns_true_when_params_empty(): void
    {
        $result = $this->repository->store([]);

        $this->assertTrue($result);
    }

    public function test_store_upserts_settings(): void
    {
        $result = $this->repository->store([
            'test_basic' => ['SITENAME' => 'MySite', 'BASEURL' => 'http://example.com'],
        ]);

        $this->assertGreaterThan(0, $result);
        $sitename = DB::table('settings')->where('name', 'test_basic.SITENAME')->first();
        $this->assertNotNull($sitename);
        $this->assertSame('MySite', $sitename->value);
        $baseurl = DB::table('settings')->where('name', 'test_basic.BASEURL')->first();
        $this->assertNotNull($baseurl);
        $this->assertSame('http://example.com', $baseurl->value);
    }

    public function test_store_updates_existing_setting(): void
    {
        DB::table('settings')->insert([
            ['name' => 'test_basic.SITENAME', 'value' => 'OldName', 'autoload' => 1],
        ]);

        $this->repository->store([
            'test_basic' => ['SITENAME' => 'NewName'],
        ]);

        $sitename = DB::table('settings')->where('name', 'test_basic.SITENAME')->first();
        $this->assertNotNull($sitename);
        $this->assertSame('NewName', $sitename->value);
    }

    public function test_store_serializes_array_values_as_json(): void
    {
        $this->repository->store([
            'test_torrent' => ['tags' => ['a', 'b']],
        ]);

        $row = DB::table('settings')->where('name', 'test_torrent.tags')->first();
        $this->assertNotNull($row);
        $decoded = json_decode((string) $row->value, true);
        $this->assertSame(['a', 'b'], $decoded);
    }

    public function test_save_batch_inserts_settings(): void
    {
        $this->repository->saveBatch('test_basic', ['SITENAME' => 'MySite', 'BASEURL' => 'http://example.com']);

        $sitename = DB::table('settings')->where('name', 'test_basic.SITENAME')->first();
        $this->assertNotNull($sitename);
        $this->assertSame('MySite', $sitename->value);
    }

    public function test_save_batch_lowercases_prefix(): void
    {
        $this->repository->saveBatch('TEST_BASIC', ['SITENAME' => 'MySite']);

        $this->assertSame(1, DB::table('settings')->where('name', 'test_basic.SITENAME')->count());
    }

    public function test_save_batch_serializes_array_values(): void
    {
        $this->repository->saveBatch('test_torrent', ['tags' => ['x', 'y']]);

        $row = DB::table('settings')->where('name', 'test_torrent.tags')->first();
        $this->assertNotNull($row);
        $decoded = json_decode((string) $row->value, true);
        $this->assertSame(['x', 'y'], $decoded);
    }

    public function test_save_batch_updates_existing_setting(): void
    {
        DB::table('settings')->insert([
            ['name' => 'test_basic.SITENAME', 'value' => 'OldName', 'autoload' => 1],
        ]);

        $this->repository->saveBatch('test_basic', ['SITENAME' => 'NewName']);

        $row = DB::table('settings')->where('name', 'test_basic.SITENAME')->first();
        $this->assertNotNull($row);
        $this->assertSame('NewName', $row->value);
    }
}
