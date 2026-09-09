<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs\Auth;

use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass;
use App\Exceptions\InsufficientPermissionException;
use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ActorContext.
 *
 * Verifies that the immutable DTO correctly wraps the authenticated
 * user, pre-computes permissions, and provides a guest fallback.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ActorContextTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_guest_context_has_zero_id_and_no_permissions(): void
    {
        $actor = new ActorContext(
            id: 0,
            username: '',
            class: UserClass::PEASANT,
            locale: 'en',
            stylesheet: 0,
            page: 0,
            passkey: null,
            permissions: [],
            user: null,
        );

        $this->assertTrue($actor->isGuest());
        $this->assertFalse($actor->isAuthenticated());
        $this->assertFalse($actor->can(PermissionEnum::SB_MANAGE));
    }

    public function test_authenticated_context_has_id_and_username(): void
    {
        $actor = new ActorContext(
            id: 42,
            username: 'testuser',
            class: UserClass::USER,
            locale: 'en',
            stylesheet: 1,
            page: 0,
            passkey: 'abc123',
            permissions: [PermissionEnum::SB_MANAGE],
            user: null,
        );

        $this->assertSame(42, $actor->id);
        $this->assertSame('testuser', $actor->username);
        $this->assertSame(UserClass::USER, $actor->class);
        $this->assertTrue($actor->isAuthenticated());
        $this->assertFalse($actor->isGuest());
    }

    public function test_can_checks_precomputed_permissions(): void
    {
        $actor = new ActorContext(
            id: 1,
            username: 'admin',
            class: UserClass::SYSOP,
            locale: 'en',
            stylesheet: 0,
            page: 0,
            passkey: 'pk',
            permissions: [PermissionEnum::SB_MANAGE, PermissionEnum::UPLOAD],
            user: null,
        );

        $this->assertTrue($actor->can(PermissionEnum::SB_MANAGE));
        $this->assertTrue($actor->can(PermissionEnum::UPLOAD));
        $this->assertFalse($actor->can(PermissionEnum::BE_ANONYMOUS));
    }

    public function test_assert_can_throws_when_permission_missing(): void
    {
        $actor = new ActorContext(
            id: 1,
            username: 'user',
            class: UserClass::PEASANT,
            locale: 'en',
            stylesheet: 0,
            page: 0,
            passkey: 'pk',
            permissions: [],
            user: null,
        );

        $this->expectException(InsufficientPermissionException::class);
        $actor->assertCan(PermissionEnum::SB_MANAGE);
    }

    public function test_to_legacy_array_returns_user_array_when_user_set(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'class' => UserClass::USER->value,
        ]);

        $actor = new ActorContext(
            id: (int) $user->id,
            username: (string) $user->username,
            class: UserClass::USER,
            locale: 'en',
            stylesheet: 0,
            page: 0,
            passkey: $user->passkey,
            permissions: [],
            user: $user,
        );

        $legacy = $actor->toLegacyArray();

        $this->assertSame((int) $user->id, (int) $legacy['id']);
        $this->assertSame($user->username, $legacy['username']);
    }

    public function test_to_legacy_array_returns_minimal_for_guest(): void
    {
        $actor = new ActorContext(
            id: 0,
            username: '',
            class: UserClass::PEASANT,
            locale: 'en',
            stylesheet: 0,
            page: 0,
            passkey: null,
            permissions: [],
            user: null,
        );

        $legacy = $actor->toLegacyArray();

        $this->assertSame(0, (int) $legacy['id']);
        $this->assertSame('', $legacy['username']);
        $this->assertSame(UserClass::PEASANT->value, (int) $legacy['class']);
    }

    public function test_from_auth_returns_guest_when_not_authenticated(): void
    {
        // Ensure CurrentUser returns null (no auth)
        $currentUser = new CurrentUser;
        $currentUser->set(null);
        $this->app->instance(CurrentUser::class, $currentUser);

        $actor = ActorContext::fromAuth();

        $this->assertTrue($actor->isGuest());
        $this->assertSame(0, $actor->id);
    }
}
