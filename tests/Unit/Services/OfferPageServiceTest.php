<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OfferPageService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for OfferPageService.
 *
 * Covers build(): action routing (list, add_offer, off_details, edit_offer,
 * offer_vote), offers-disabled abort, permission-denied for add_offer,
 * zero-id abort for off_details, nonexistent offer returns empty for
 * off_details and edit_offer, invalid sort abort, empty list results,
 * and common data keys in the returned array.
 *
 * Repositories (OfferRepository, UsercpRepository, CategoryRepository)
 * are final and cannot be mocked, so real DB rows are inserted via
 * DB::table() to avoid Scout/MeiliSearch indexing.
 */
final class OfferPageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $initialObLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
        Redis::connection()->flushdb();

        if (! defined('IN_NEXUS')) {
            define('IN_NEXUS', true);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('offers')->truncate();
        DB::table('offervotes')->truncate();
        DB::table('users')->truncate();
        DB::table('categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }

        Mockery::close();
        parent::tearDown();
    }

    private function service(): OfferPageService
    {
        return new OfferPageService;
    }

    private function insertUser(string $username = 'testuser', int $class = 1): int
    {
        return (int) DB::table('users')->insertGetId([
            'username' => $username.uniqid(),
            'email' => $username.uniqid().'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => $class,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
        ]);
    }

    private function insertCategory(string $name = 'Test Cat', int $mode = 1): int
    {
        return (int) DB::table('categories')->insertGetId([
            'mode' => $mode,
            'class_name' => 'test_cat',
            'name' => $name,
            'image' => 'test.gif',
            'sort_index' => 0,
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertOffer(int $userId, int $categoryId, array $overrides = []): int
    {
        return (int) DB::table('offers')->insertGetId(array_merge([
            'userid' => $userId,
            'name' => 'Test Offer',
            'descr' => 'A test offer description',
            'added' => now()->toDateTimeString(),
            'allowedtime' => now()->toDateTimeString(),
            'yeah' => 0,
            'against' => 0,
            'category' => $categoryId,
            'comments' => 0,
            'allowed' => 'pending',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $userData */
    private function authenticatedUser(array $userData = []): void
    {
        $defaults = [
            'id' => 1,
            'username' => 'testuser',
            'class' => 1,
        ];

        $currentUser = new CurrentUser;
        $currentUser->set(array_merge($defaults, $userData));
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    /** @param  array<string, mixed>  $values */
    private function mockGlobals(array $values = []): void
    {
        $globals = new Globals;
        $defaults = [
            'BASEURL' => 'http://test.com',
            'CONTENT_WIDTH' => '737',
            'browsecatmode' => 1,
            'enableoffer' => 'yes',
            'minoffervotes' => 5,
            'offervotetimeout_main' => 0,
            'offeruptimeout_main' => 0,
            'offervote_bonus' => 0,
            'upload_class' => 3,
            'addoffer_class' => 2,
            'againstoffer_class' => 13,
        ];
        foreach (array_merge($defaults, $values) as $key => $value) {
            $globals->set($key, $value);
        }
        $this->app->instance(Globals::class, $globals);
    }

    private function mockCache(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get_value')->andReturn(false);
        $this->app->instance(LegacyRedisCache::class, $cache);
    }

    /**
     * Call the service while suppressing E_NOTICE/E_WARNING from the
     * legacy rendering system triggered by LegacyResponse::abort().
     */
    private function callService(Request $request): mixed
    {
        set_error_handler(function (int $severity): bool {
            return true;
        }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $this->service()->build($request);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Assert that calling the service with $request triggers an abort/guard.
     * Any Throwable from the guard path indicates the abort was triggered.
     */
    private function assertServiceThrows(Request $request): void
    {
        $threw = false;
        try {
            $this->callService($request);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception was not thrown');
    }

    // --- offers disabled ---

    public function test_build_aborts_when_offers_disabled(): void
    {
        $this->authenticatedUser();
        $this->mockGlobals(['enableoffer' => 'no']);
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET');

        $this->assertServiceThrows($request);
    }

    // --- list action (default) with empty results ---

    public function test_build_list_action_returns_list_data_with_empty_results(): void
    {
        $userId = $this->insertUser();
        $this->insertCategory();

        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET');

        $data = $this->callService($request);

        $this->assertIsArray($data);
        $this->assertSame('list', $data['action']);
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('rules', $data['list']);
        $this->assertArrayHasKey('searchBox', $data['list']);
        $this->assertFalse($data['list']['hasRows']);
        $this->assertSame(0, $data['list']['count']);
    }

    // --- list action with an offer present ---

    public function test_build_list_action_with_offer_shows_rows(): void
    {
        $userId = $this->insertUser();
        $catId = $this->insertCategory();
        $this->insertOffer($userId, $catId, ['name' => 'My Offer']);

        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET');

        $data = $this->callService($request);

        $this->assertSame('list', $data['action']);
        $this->assertTrue($data['list']['hasRows']);
        $this->assertSame(1, $data['list']['count']);
    }

    // --- list action resolves by default ---

    public function test_build_resolves_list_action_when_no_action_param(): void
    {
        $userId = $this->insertUser();
        $this->insertCategory();

        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET');

        $data = $this->callService($request);

        $this->assertSame('list', $data['action']);
    }

    // --- add_offer action without permission ---

    public function test_build_add_offer_aborts_without_permission(): void
    {
        $this->authenticatedUser(['class' => 1]);
        $this->mockGlobals();
        $this->mockCache();

        // No user authenticated via Auth, so Permission::can returns false
        $request = Request::create('/offers.php', 'GET', ['add_offer' => '1']);

        $this->assertServiceThrows($request);
    }

    // --- off_details with zero id ---

    public function test_build_off_details_aborts_with_zero_id(): void
    {
        $this->authenticatedUser();
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET', ['off_details' => '1']);

        $this->assertServiceThrows($request);
    }

    // --- off_details with nonexistent offer ---

    public function test_build_off_details_returns_empty_for_nonexistent_offer(): void
    {
        $this->authenticatedUser();
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET', ['off_details' => '1', 'id' => '999']);

        ob_start();
        $data = $this->callService($request);
        ob_end_clean();

        $this->assertSame('off_details', $data['action']);
        $this->assertSame([], $data['off_details']);
    }

    // --- edit_offer with nonexistent offer ---

    public function test_build_edit_offer_returns_empty_for_nonexistent_offer(): void
    {
        $this->authenticatedUser();
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET', ['edit_offer' => '1', 'id' => '999']);

        ob_start();
        $data = $this->callService($request);
        ob_end_clean();

        $this->assertSame('edit_offer', $data['action']);
        $this->assertSame([], $data['edit_offer']);
    }

    // --- offer_vote action ---

    public function test_build_offer_vote_returns_vote_list_data(): void
    {
        $userId = $this->insertUser();
        $catId = $this->insertCategory();
        $offerId = $this->insertOffer($userId, $catId, ['name' => 'Vote Test Offer']);

        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET', ['offer_vote' => '1', 'id' => (string) $offerId]);

        $data = $this->callService($request);

        $this->assertSame('offer_vote', $data['action']);
        $this->assertArrayHasKey('offer_vote', $data);
        $this->assertSame($offerId, $data['offer_vote']['offerId']);
        $this->assertSame('Vote Test Offer', $data['offer_vote']['offerName']);
        $this->assertFalse($data['offer_vote']['hasVotes']);
        $this->assertSame([], $data['offer_vote']['rows']);
    }

    // --- invalid sort ---

    public function test_build_list_with_invalid_sort_aborts(): void
    {
        $this->authenticatedUser();
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET', ['sort' => 'malicious_column']);

        $this->assertServiceThrows($request);
    }

    // --- common data keys ---

    public function test_build_includes_common_data_keys(): void
    {
        $userId = $this->insertUser();
        $this->insertCategory();

        $this->authenticatedUser(['id' => $userId, 'username' => 'tester']);
        $this->mockGlobals();
        $this->mockCache();

        $request = Request::create('/offers.php', 'GET');

        $data = $this->callService($request);

        $this->assertSame($userId, $data['userId']);
        $this->assertArrayHasKey('lang', $data);
        $this->assertArrayHasKey('curUser', $data);
        $this->assertArrayHasKey('baseUrl', $data);
        $this->assertArrayHasKey('contentWidth', $data);
        $this->assertArrayHasKey('enableoffer', $data);
        $this->assertArrayHasKey('minoffervotes', $data);
        $this->assertSame('yes', $data['enableoffer']);
        $this->assertSame(5, $data['minoffervotes']);
    }

    // --- action resolution: zero value params are ignored ---

    public function test_build_ignores_action_param_with_zero_value(): void
    {
        $userId = $this->insertUser();
        $this->insertCategory();

        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();
        $this->mockCache();

        // add_offer=0 should be ignored, defaulting to list
        $request = Request::create('/offers.php', 'GET', ['add_offer' => '0']);

        $data = $this->callService($request);

        $this->assertSame('list', $data['action']);
    }
}
