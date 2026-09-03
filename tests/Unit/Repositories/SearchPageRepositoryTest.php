<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\SearchPageRepository;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for SearchPageRepository.
 *
 * Covers dataForSearch() with MeiliSearch disabled, exercising the SQL
 * fallback path and the empty-search early return.
 *
 * The Settings static cache is set directly to disable MeiliSearch and
 * configure the browse category, then reset in tearDown to avoid
 * polluting other tests.
 */
final class SearchPageRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private SearchPageRepository $repository;

    private int $categoryId;

    private int $categoryMode;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::resetState();

        // Find an existing category from the seeded data
        $category = DB::table('categories')->first();
        $this->categoryId = $category ? (int) $category->id : 1;
        $this->categoryMode = $category ? (int) $category->mode : 1;

        // Disable MeiliSearch, set browse_cat to match the seeded category mode,
        // and make all approval statuses visible so the query is not filtered.
        $settingsRef = new \ReflectionClass(Settings::class);
        $prop = $settingsRef->getProperty('settings');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'meilisearch' => ['enabled' => 'no'],
            'main' => ['browsecat' => (string) $this->categoryMode],
            'torrent' => ['approval_status_none_visible' => 'yes'],
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new SearchPageRepository;
    }

    protected function tearDown(): void
    {
        $settingsRef = new \ReflectionClass(Settings::class);
        $prop = $settingsRef->getProperty('settings');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    public function test_data_for_search_returns_empty_results_with_empty_search(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $request = Request::create('/browse.php', 'GET');

        $result = $this->repository->dataForSearch($request, $user);

        $this->assertSame('', $result['search']);
        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['rows']);
        $this->assertFalse($result['hasResults']);
    }

    public function test_data_for_search_returns_results_via_sql_fallback(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createTorrent($user->id, 'Inception Movie 2010');
        $this->createTorrent($user->id, 'Another Torrent');

        $request = Request::create('/browse.php', 'GET', [
            'search' => 'Inception',
            'search_area' => 0,
        ]);

        $result = $this->repository->dataForSearch($request, $user);

        $this->assertSame('Inception', $result['search']);
        $this->assertSame(1, $result['count']);
        $this->assertTrue($result['hasResults']);
        $this->assertCount(1, $result['rows']);
    }

    public function test_data_for_search_returns_no_results_when_search_does_not_match(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createTorrent($user->id, 'Inception Movie');

        $request = Request::create('/browse.php', 'GET', [
            'search' => 'NonExistentTerm',
            'search_area' => 0,
        ]);

        $result = $this->repository->dataForSearch($request, $user);

        $this->assertSame(0, $result['count']);
        $this->assertFalse($result['hasResults']);
        $this->assertSame([], $result['rows']);
    }

    public function test_data_for_search_strips_dots_from_search_term(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createTorrent($user->id, 'Mr Robot S01');

        // "Mr.Robot" should become "Mr Robot" for the SQL LIKE query.
        $request = Request::create('/browse.php', 'GET', [
            'search' => 'Mr.Robot',
            'search_area' => 0,
        ]);

        $result = $this->repository->dataForSearch($request, $user);

        $this->assertSame('Mr.Robot', $result['search']);
        $this->assertSame(1, $result['count']);
        $this->assertTrue($result['hasResults']);
    }

    public function test_data_for_search_includes_torrentsperpage_in_result(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $request = Request::create('/browse.php', 'GET');

        $result = $this->repository->dataForSearch($request, $user);

        $this->assertGreaterThan(0, $result['torrentsperpage']);
    }

    public function test_data_for_search_escapes_search_in_searchstr_ori(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $request = Request::create('/browse.php', 'GET', [
            'search' => '<script>alert(1)</script>',
            'search_area' => 0,
        ]);

        $result = $this->repository->dataForSearch($request, $user);

        $this->assertStringContainsString('&lt;script&gt;', $result['searchstr_ori']);
        $this->assertStringNotContainsString('<script>', $result['searchstr_ori']);
    }

    private function createTorrent(int $ownerId, string $name): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => $name,
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => $this->categoryId,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'approval_status' => 1,
            'added' => now()->toDateTimeString(),
        ]);
    }
}
