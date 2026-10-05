<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTOs\Auth\ActorContext;
use App\Enums\UserClass;
use App\Repositories\ShoutboxRepository;
use App\Repositories\UserPasskeyRepository;
use App\Services\Ajax\AjaxFeatureServices;
use App\Services\Ajax\PasskeyActions;
use App\Services\Ajax\ShoutboxActions;
use App\Services\AjaxService;
use App\Services\ShoutboxService;
use App\Support\CurrentUser;
use App\Support\NotificationFeed;
use App\Support\Shoutbox;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for AjaxService.
 *
 * Covers the ALLOWED_ACTIONS whitelist, dispatch routing for the two
 * remaining action groups (shoutbox, passkey) and their validation.
 * The actions migrated to REST endpoints are covered end-to-end by
 * AjaxRestEndpointsTest (redirect + envelope contract).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class AjaxServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AjaxService $service;

    /** @var UserPasskeyRepository&MockInterface */
    private UserPasskeyRepository $passkeyRepo;

    private CurrentUser $currentUser;

    private ActorContext $actorContext;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('shoutbox')->delete();
        DB::table('shoutbox_reactions')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /** @var UserPasskeyRepository&MockInterface $passkeyRepo */
        $passkeyRepo = Mockery::mock(UserPasskeyRepository::class);
        $this->passkeyRepo = $passkeyRepo;

        $this->currentUser = new CurrentUser;
        $this->app->instance(CurrentUser::class, $this->currentUser);

        $this->actorContext = new ActorContext(
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

        $this->service = $this->makeService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): AjaxService
    {
        return new AjaxService(
            new AjaxFeatureServices(
                new ShoutboxActions(new ShoutboxService(new ShoutboxRepository), $this->actorContext),
                new PasskeyActions($this->passkeyRepo, $this->currentUser),
                app(NotificationFeed::class),
            ),
        );
    }

    /** @param array<string, mixed> $overrides */
    private function createUser(array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 1,
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
        ], $overrides));
    }

    private function authenticateUser(int $userId): void
    {
        $this->currentUser->set([
            'id' => $userId,
            'username' => 'testuser',
            'class' => 1,
        ]);

        $this->actorContext = new ActorContext(
            id: $userId,
            username: 'testuser',
            class: UserClass::tryFrom(1) ?? UserClass::PEASANT,
            locale: 'en',
            stylesheet: 0,
            page: 0,
            passkey: null,
            permissions: [],
            user: null,
        );

        $this->service = $this->makeService();
    }

    // --- ALLOWED_ACTIONS ---

    public function test_allowed_actions_contains_expected_entries(): void
    {
        $this->assertContains('deletePasskey', AjaxService::ALLOWED_ACTIONS);
        $this->assertContains('shoutboxPost', AjaxService::ALLOWED_ACTIONS);
        $this->assertContains('clearShoutBox', AjaxService::ALLOWED_ACTIONS);
    }

    public function test_allowed_actions_does_not_contain_arbitrary_method(): void
    {
        $this->assertNotContains('nonExistentAction', AjaxService::ALLOWED_ACTIONS);
    }

    public function test_dispatch_throws_for_unknown_action(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown ajax action');

        $this->service->dispatch('nonExistentAction', []);
    }

    // --- shoutboxPost ---

    public function test_shoutbox_post_throws_for_empty_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message cannot be empty');

        $this->service->dispatch('shoutboxPost', ['text' => '   ']);
    }

    public function test_shoutbox_post_throws_for_too_long_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message too long');

        $this->service->dispatch('shoutboxPost', ['text' => str_repeat('x', Shoutbox::MAX_MESSAGE_LENGTH + 1)]);
    }

    public function test_shoutbox_post_succeeds_with_valid_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $result = $this->service->dispatch('shoutboxPost', ['text' => 'Hello world']);

        $this->assertTrue($result);
        $this->assertSame(1, DB::table('shoutbox')->count());
    }

    // --- shoutboxEdit ---

    public function test_shoutbox_edit_throws_for_invalid_id(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $this->service->dispatch('shoutboxEdit', ['id' => 0, 'text' => 'Hello']);
    }

    public function test_shoutbox_edit_throws_for_empty_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $this->service->dispatch('shoutboxEdit', ['id' => 1, 'text' => '   ']);
    }

    public function test_shoutbox_edit_throws_for_too_long_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message too long');

        $this->service->dispatch('shoutboxEdit', ['id' => 1, 'text' => str_repeat('x', Shoutbox::MAX_MESSAGE_LENGTH + 1)]);
    }

    // --- shoutboxDelete ---

    public function test_shoutbox_delete_throws_for_invalid_id(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $this->service->dispatch('shoutboxDelete', ['id' => 0]);
    }

    public function test_shoutbox_delete_throws_for_negative_id(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->dispatch('shoutboxDelete', ['id' => -1]);
    }

    // --- shoutboxReact ---

    public function test_shoutbox_react_throws_for_null_result(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        // Nonexistent message id → toggleReaction returns null
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reaction or reacting too often');

        $this->service->dispatch('shoutboxReact', ['id' => 99999, 'reaction' => 'invalid']);
    }

    // --- clearShoutBox ---

    public function test_clear_shout_box_throws_when_no_permission(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        // Non-admin user → clearAll returns false → RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No permission');

        $this->service->dispatch('clearShoutBox', []);
    }

    // --- passkey actions ---

    public function test_get_passkey_list_delegates_to_passkey_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $expectedList = [['id' => 1, 'name' => 'Key 1']];
        $this->passkeyRepo->shouldReceive('getList')
            ->with($userId)
            ->once()
            ->andReturn($expectedList);

        $result = $this->service->dispatch('getPasskeyList', []);

        $this->assertSame($expectedList, $result);
    }

    public function test_get_passkey_create_args_delegates_to_passkey_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $expectedArgs = ['challenge' => 'abc123'];
        $this->passkeyRepo->shouldReceive('getCreateArgs')
            ->with($userId, 'testuser')
            ->once()
            ->andReturn($expectedArgs);

        $result = $this->service->dispatch('getPasskeyCreateArgs', []);

        $this->assertSame($expectedArgs, $result);
    }

    public function test_delete_passkey_delegates_to_passkey_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->passkeyRepo->shouldReceive('delete')
            ->with($userId, 'cred-123')
            ->once()
            ->andReturn(true);

        $result = $this->service->dispatch('deletePasskey', ['credentialId' => 'cred-123']);

        $this->assertTrue($result);
    }
}
