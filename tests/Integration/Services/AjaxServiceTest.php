<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTOs\Auth\ActorContext;
use App\Enums\UserClass;
use App\Repositories\UserPasskeyRepository;
use App\Services\Ajax\AjaxFeatureServices;
use App\Services\Ajax\PasskeyActions;
use App\Services\AjaxService;
use App\Support\CurrentUser;
use App\Support\NotificationFeed;
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
 * Covers the ALLOWED_ACTIONS whitelist and dispatch routing for the
 * remaining passkey action group. The shoutbox group migrated to REST
 * endpoints and is covered end-to-end by AjaxRestEndpointsTest
 * (redirect pins + envelope contract).
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
        $this->assertContains('getPasskeyList', AjaxService::ALLOWED_ACTIONS);
    }

    public function test_allowed_actions_does_not_contain_arbitrary_method(): void
    {
        $this->assertNotContains('nonExistentAction', AjaxService::ALLOWED_ACTIONS);
    }

    public function test_allowed_actions_excludes_migrated_groups(): void
    {
        $this->assertNotContains('shoutboxPost', AjaxService::ALLOWED_ACTIONS);
        $this->assertNotContains('clearShoutBox', AjaxService::ALLOWED_ACTIONS);
    }

    public function test_dispatch_throws_for_unknown_action(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown ajax action');

        $this->service->dispatch('nonExistentAction', []);
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
