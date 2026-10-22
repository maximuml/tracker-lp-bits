<?php

namespace Tests\Integration\Auth;

use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Auth;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pins NexusWebGuard's ambient-state contract: login() must promote the
 * raw legacy row (passkey/auth_key included — the same shape the cookie
 * path produces), logout() must clear it. Regression guard for the
 * toArray() → promote() unification that kept post-login requests from
 * reading a stripped actor row.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class NexusWebGuardTest extends TestCase
{
    public function test_login_promotes_raw_legacy_row(): void
    {
        $user = User::factory()->create();

        Auth::guard('nexus-web')->login($user);

        $row = CurrentUser::instance()->get();
        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row['id']);
        $this->assertSame($user->passkey, $row['passkey']);
        $this->assertSame($user->auth_key, $row['auth_key']);
        $this->assertSame($user->toLegacyArray(), $row);
    }

    public function test_logout_clears_ambient_user(): void
    {
        $user = User::factory()->create();
        CurrentUser::instance()->promote($user);
        $this->assertNotNull(CurrentUser::instance()->get());

        Auth::guard('nexus-web')->logout();

        $this->assertNull(CurrentUser::instance()->get());
    }

    public function test_promote_if_absent_does_not_clobber_resolved_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        CurrentUser::instance()->promote($first);

        CurrentUser::instance()->promoteIfAbsent($second);

        $this->assertSame($first->id, (int) CurrentUser::instance()->get()['id']);
    }

    public function test_promote_if_absent_seeds_when_empty(): void
    {
        CurrentUser::instance()->reset();
        $user = User::factory()->create();

        CurrentUser::instance()->promoteIfAbsent($user);

        $this->assertSame($user->id, (int) CurrentUser::instance()->get()['id']);
    }
}
