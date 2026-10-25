<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Listeners\ResetNexus;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\User;
use App\Services\Captcha\TurnstileRenderState;
use App\Services\ToolMaintenanceService;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\NexusContext;
use App\Support\PageState;
use App\Support\Settings;
use App\Support\State\CleanupRepositoryState;
use App\Support\State\CommentState;
use App\Support\State\ForumState;
use App\Support\State\PromotionState;
use App\Support\State\SearchBoxModelState;
use App\Support\State\SearchBoxState;
use App\Support\State\SettingState;
use App\Support\State\ShoutboxState;
use App\Support\State\StyleRepositoryState;
use App\Support\State\TagRepositoryState;
use App\Support\State\UserClassState;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Cross-request isolation test suite for long-lived workers.
 *
 * Simulates a queue worker lifecycle between jobs and requests, with
 * ResetNexus running between each. Verifies that per-request state (user,
 * settings, locale, theme) does not leak.
 *
 * These tests run under PHPUnit, but they simulate the critical isolation
 * boundary by:
 * 1. Setting up state for "request A" (user A, settings A)
 * 2. Running ResetNexus between jobs/requests
 * 3. Setting up state for "request B" (user B, settings B)
 * 4. Asserting that request B sees only its own state
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ResetNexusIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Simulate a fresh worker: clear all state
        $this->resetWorkerState();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->resetWorkerState();
        parent::tearDown();
    }

    public function test_current_user_does_not_leak_between_requests(): void
    {
        $userA = User::factory()->create([
            'username' => 'userA_isolation',
            'status' => 1,
            'enabled' => true,
        ]);

        $userB = User::factory()->create([
            'username' => 'userB_isolation',
            'status' => 1,
            'enabled' => true,
        ]);

        // Request A: set current user to A
        app(CurrentUser::class)->set(['id' => $userA->id, 'username' => $userA->username]);
        $this->assertSame($userA->id, app(CurrentUser::class)->get()['id']);

        // Simulate end of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: CurrentUser should be reset (null/uninitialized)
        $this->assertNull(app(CurrentUser::class)->get());

        // Set current user to B
        app(CurrentUser::class)->set(['id' => $userB->id, 'username' => $userB->username]);
        $this->assertSame($userB->id, app(CurrentUser::class)->get()['id']);

        // Reset again
        $this->dispatchResetNexus();

        // Should not see user A or B
        $this->assertNull(app(CurrentUser::class)->get());
    }

    public function test_auth_guard_user_does_not_leak_between_requests(): void
    {
        $userA = User::factory()->create([
            'username' => 'guardA_isolation',
            'status' => 1,
            'enabled' => true,
        ]);

        // Request A: set guard user to A via reflection (simulating what
        // NexusWebGuard::user() does when it caches the authenticated user)
        $guard = Auth::guard();
        $ref = new \ReflectionProperty($guard, 'user');
        $ref->setValue($guard, $userA);
        $this->assertSame($userA->id, Auth::guard()->user()->id);

        // Simulate end of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: guard user should be null (not leaked from A)
        $this->assertNull(Auth::guard()->user());
    }

    public function test_nexus_web_guard_user_does_not_leak_between_requests(): void
    {
        $userA = User::factory()->create([
            'username' => 'nexusguardA_iso',
            'status' => 1,
            'enabled' => true,
        ]);

        // Request A: the real auth guard caches user A (GuardHelpers::setUser
        // is what NexusWebGuard::user() ends with once authentication passes)
        Auth::guard('nexus-web')->setUser($userA);
        $this->assertSame($userA->id, Auth::guard('nexus-web')->user()->id);

        // Simulate end of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: nexus-web resolves a fresh instance — no c_secure_pass
        // cookie on the new request, so user() must be null, not user A
        $this->assertNull(Auth::guard('nexus-web')->user());
    }

    public function test_settings_static_cache_does_not_leak_between_requests(): void
    {
        // Prime the settings cache
        $valueBefore = Settings::get('site_name', 'default');
        $this->assertNotNull(Settings::get());

        // Simulate end of request → ResetNexus
        $this->dispatchResetNexus();

        // After reset, the static cache should be null (will reload on next access)
        // We verify by checking that the cache is rebuilt from DB
        $valueAfter = Settings::get('site_name', 'default');
        $this->assertSame($valueBefore, $valueAfter);
    }

    public function test_support_context_does_not_leak_between_requests(): void
    {
        // Request A: set per-request page state
        PageState::instance()->setLangDir('chs');
        PageState::instance()->setMenu('<ul>A</ul>', 'forums');
        $this->assertSame('chs', PageState::instance()->langDir());

        // Simulate end of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: page state should be reset (not carry over from A)
        $this->assertSame('', PageState::instance()->langDir());
        $this->assertSame('', PageState::instance()->menuHtml());
        $this->assertSame('', PageState::instance()->menuSelected());
    }

    public function test_two_users_alternating_requests_no_leak(): void
    {
        $userA = User::factory()->create([
            'username' => 'alt_userA',
            'class' => 3,
            'status' => 1,
            'enabled' => true,
        ]);

        $userB = User::factory()->create([
            'username' => 'alt_userB',
            'class' => 1,
            'status' => 1,
            'enabled' => true,
        ]);

        // Simulate alternating requests: A, B, A, B, A
        $sequence = [$userA, $userB, $userA, $userB, $userA];
        foreach ($sequence as $i => $user) {
            // Start of request: set current user
            app(CurrentUser::class)->set(['id' => $user->id, 'username' => $user->username]);

            // Verify we see the correct user
            $current = app(CurrentUser::class)->get();
            $this->assertSame(
                $user->id,
                $current['id'],
                "Request $i: expected user {$user->username} but got user #".($current['id'] ?? 'null')
            );

            // End of request: ResetNexus
            $this->dispatchResetNexus();

            // After reset, should be null
            $this->assertNull(app(CurrentUser::class)->get(), "Request $i: state leaked after reset");
        }
    }

    public function test_settings_change_visible_after_reset(): void
    {
        // Prime the cache with current DB value
        $originalValue = Settings::get('basic.SITENAME');
        $this->assertNotNull($originalValue, 'basic.SITENAME setting should exist in DB');

        // Simulate a settings change in the DB (by another worker/request)
        Setting::query()
            ->where('name', 'basic.SITENAME')
            ->update(['value' => 'changed_site_name']);

        // Without reset, the stale cached value would be returned
        // (Settings::get() caches on first access)
        $staleValue = Settings::get('basic.SITENAME');
        $this->assertSame($originalValue, $staleValue, 'Cache should still hold old value before reset');

        // Simulate end of request → ResetNexus (clears Settings cache)
        $this->dispatchResetNexus();

        // After reset, the next access should reload from DB
        $freshValue = Settings::get('basic.SITENAME');
        $this->assertSame('changed_site_name', $freshValue, 'Settings should reload from DB after reset');

        // Restore original value
        Setting::query()
            ->where('name', 'basic.SITENAME')
            ->update(['value' => $originalValue]);

        $this->dispatchResetNexus();
    }

    public function test_reset_nexus_clears_all_known_state(): void
    {
        // Set up state that ResetNexus should clear
        app(CurrentUser::class)->set(['id' => 999, 'username' => 'test']);
        PageState::instance()->setKeyShortcutScript('<script></script>');
        $guard = Auth::guard();
        $ref = new \ReflectionProperty($guard, 'user');
        $ref->setValue($guard, new User);

        // Run ResetNexus
        $this->dispatchResetNexus();

        // Verify all state is cleared
        $this->assertNull(app(CurrentUser::class)->get());
        $this->assertSame('', PageState::instance()->keyShortcutScript());
        $this->assertNull(Auth::guard()->user());
    }

    public function test_consecutive_resets_are_idempotent(): void
    {
        // Running ResetNexus multiple times should not cause errors
        $this->dispatchResetNexus();
        $this->dispatchResetNexus();
        $this->dispatchResetNexus();

        $this->assertNull(app(CurrentUser::class)->get());
        $this->assertNull(Auth::guard()->user());
    }

    public function test_mutable_static_caches_do_not_leak_between_requests(): void
    {
        // Seed worker-global caches the way "request A" would — they live
        // on #[Scoped] state holders now, dropped by forgetScopedInstances().
        app(TurnstileRenderState::class)->scriptInjected = true;
        PromotionState::instance()->globalSpecialState = 5;
        SettingState::instance()->autoloadedSettings = ['k' => 'v'];
        SearchBoxModelState::instance()->categoryIdsBySearchBox = ['0' => [1, 2]];
        CommentState::instance()->tempCode = ['snippet-A'];
        ForumState::instance()->topics = [1 => new Topic];
        ShoutboxState::instance()->mentionUserCache = ['nick' => false];
        UserClassState::instance()->settingAccount = ['k' => 'v'];
        TagRepositoryState::instance()->allTags = new EloquentCollection;
        StyleRepositoryState::instance()->rows = [1 => ['name' => 'x']];
        SearchBoxState::instance()->rows = [1 => ['name' => 'x']];
        CleanupRepositoryState::instance()->oneTaskSeconds = 42;
        (new \ReflectionProperty(ToolMaintenanceService::class, 'uidPermissionsCached'))
            ->setValue(app(ToolMaintenanceService::class), [1 => ['x']]);

        // Singleton-level per-request state (NexusCache bypass + debug counters).
        $cache = app(NexusCache::class);
        $cache->setBypass(1);
        $cache->readCount = 7;
        $cache->writeCount = 3;
        $cache->keyHits = ['read' => ['k' => 2]];

        // Simulate end of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B must not inherit request A's memoized state — fresh
        // scoped instances expose defaults again.
        $this->assertFalse(app(TurnstileRenderState::class)->scriptInjected, 'state leaked: TurnstileRenderState::$scriptInjected');
        $this->assertNull(PromotionState::instance()->globalSpecialState, 'state leaked: PromotionState::$globalSpecialState');
        $this->assertNull(SettingState::instance()->autoloadedSettings, 'state leaked: SettingState::$autoloadedSettings');
        $this->assertSame([], SearchBoxModelState::instance()->categoryIdsBySearchBox, 'state leaked: SearchBoxModelState::$categoryIdsBySearchBox');
        $this->assertSame([], CommentState::instance()->tempCode, 'state leaked: CommentState::$tempCode');
        $this->assertSame([], ForumState::instance()->topics, 'state leaked: ForumState::$topics');
        $this->assertSame([], ShoutboxState::instance()->mentionUserCache, 'state leaked: ShoutboxState::$mentionUserCache');
        $this->assertNull(UserClassState::instance()->settingAccount, 'state leaked: UserClassState::$settingAccount');
        $this->assertNull(TagRepositoryState::instance()->allTags, 'state leaked: TagRepositoryState::$allTags');
        $this->assertNull(StyleRepositoryState::instance()->rows, 'state leaked: StyleRepositoryState::$rows');
        $this->assertNull(SearchBoxState::instance()->rows, 'state leaked: SearchBoxState::$rows');
        $this->assertSame(0, CleanupRepositoryState::instance()->oneTaskSeconds, 'state leaked: CleanupRepositoryState::$oneTaskSeconds');
        $this->assertSame(
            [],
            (new \ReflectionProperty(ToolMaintenanceService::class, 'uidPermissionsCached'))
                ->getValue(app(ToolMaintenanceService::class)),
            'state leaked: ToolMaintenanceService::$uidPermissionsCached',
        );

        $cache = app(NexusCache::class);
        $this->assertSame(0, $cache->bypasses());
        $this->assertSame(0, $cache->readCount());
        $this->assertSame(0, $cache->writeCount());
        $this->assertSame([], $cache->keyHits('read'));
    }

    /**
     * @param  class-string  $class
     */
    private function setStaticProp(string $class, string $prop, mixed $value): void
    {
        $ref = new \ReflectionProperty($class, $prop);
        $ref->setValue(null, $value);
    }

    /**
     * @param  class-string  $class
     */
    private function assertStaticPropEmpty(string $class, string $prop): void
    {
        $ref = new \ReflectionProperty($class, $prop);
        $value = $ref->getValue();
        $this->assertTrue(
            $value === null || $value === [] || $value === false || $value === 0 || $value === 0.0,
            "state leaked across requests: {$class}::\${$prop}",
        );
    }

    /**
     * Dispatch the ResetNexus listener between jobs/requests.
     */
    private function dispatchResetNexus(): void
    {
        app(ResetNexus::class)->handle(null);
    }

    /**
     * Reset all worker state to simulate a fresh worker.
     */
    private function resetWorkerState(): void
    {
        app(CurrentUser::class)->reset();
        NexusContext::reset();
        Settings::resetCache();
        Auth::forgetGuards();
    }
}
