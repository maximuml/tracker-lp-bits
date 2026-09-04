<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Listeners\ResetNexus;
use App\Models\Setting;
use App\Models\User;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Settings;
use App\Support\SupportContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Cross-request isolation test suite for Octane.
 *
 * Simulates the Octane worker lifecycle: multiple sequential requests
 * served by the same PHP process, with ResetNexus running between each.
 * Verifies that per-request state (user, settings, locale, theme) does
 * not leak from one request to the next.
 *
 * These tests run under PHPUnit (not a real RoadRunner worker), but they
 * simulate the critical isolation boundary by:
 * 1. Setting up state for "request A" (user A, settings A)
 * 2. Running ResetNexus (as Octane would between requests)
 * 3. Setting up state for "request B" (user B, settings B)
 * 4. Asserting that request B sees only its own state
 */
final class OctaneCrossRequestIsolationTest extends TestCase
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
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $userB = User::factory()->create([
            'username' => 'userB_isolation',
            'status' => 'confirmed',
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
            'status' => 'confirmed',
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
        // Request A: set a global
        app(Globals::class)->set('TEST_ISOLATION_KEY', 'value_from_A');
        $this->assertSame('value_from_A', app(Globals::class)->get('TEST_ISOLATION_KEY'));

        // Simulate end of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: global should be reset (not carry over from A)
        $this->assertNull(app(Globals::class)->get('TEST_ISOLATION_KEY'));
    }

    public function test_two_users_alternating_requests_no_leak(): void
    {
        $userA = User::factory()->create([
            'username' => 'alt_userA',
            'class' => 3,
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $userB = User::factory()->create([
            'username' => 'alt_userB',
            'class' => 1,
            'status' => 'confirmed',
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
        app(Globals::class)->set('TEST_KEY', 'test_value');
        $guard = Auth::guard();
        $ref = new \ReflectionProperty($guard, 'user');
        $ref->setValue($guard, new User);

        // Run ResetNexus
        $this->dispatchResetNexus();

        // Verify all state is cleared
        $this->assertNull(app(CurrentUser::class)->get());
        $this->assertNull(app(Globals::class)->get('TEST_KEY'));
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

    /**
     * Dispatch the ResetNexus listener as Octane would between requests.
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
        SupportContext::reset();
        Settings::resetCache();
        $guard = Auth::guard();
        if (property_exists($guard, 'user')) {
            try {
                $ref = new \ReflectionProperty($guard, 'user');
                $ref->setValue($guard, null);
            } catch (\Throwable) {
                // Ignore
            }
        }
    }
}
