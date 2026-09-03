<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Enums\UserClass;
use App\Http\Controllers\LogController;
use App\Models\User;
use App\Repositories\LogRepository;
use App\Repositories\ToolRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class LogControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::resetState();
        Cache::flush();
        $this->setupLegacyEnvironment();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_legacy_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'GET');
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_legacy_denies_access_for_guest_with_chronicle_action(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'GET', ['action' => 'chronicle']);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_legacy_denies_access_for_guest_with_poll_action(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'GET', ['action' => 'poll']);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_legacy_returns_invalid_action_for_staffleader(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'GET', ['action' => 'invalid']);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid Action', (string) $response->getContent());
    }

    public function test_chronicle_add_redirects_for_staffleader(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        /** @var LogRepository&MockInterface $logRepository */
        $logRepository = Mockery::mock(LogRepository::class);
        $logRepository->shouldReceive('addChronicle')->once()->with($user->id, 'test entry');
        app()->instance(LogRepository::class, $logRepository);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'POST', [
            'action' => 'chronicle',
            'do' => 'add',
            'txt' => 'test entry',
        ]);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/log.php?action=chronicle', $response->getTargetUrl());
    }

    public function test_chronicle_add_with_empty_txt_still_redirects(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        /** @var LogRepository&MockInterface $logRepository */
        $logRepository = Mockery::mock(LogRepository::class);
        $logRepository->shouldNotReceive('addChronicle');
        app()->instance(LogRepository::class, $logRepository);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'POST', [
            'action' => 'chronicle',
            'do' => 'add',
            'txt' => '',
        ]);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/log.php?action=chronicle', $response->getTargetUrl());
    }

    public function test_chronicle_update_with_zero_id_redirects(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        /** @var LogRepository&MockInterface $logRepository */
        $logRepository = Mockery::mock(LogRepository::class);
        $logRepository->shouldNotReceive('updateChronicle');
        app()->instance(LogRepository::class, $logRepository);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'POST', [
            'action' => 'chronicle',
            'do' => 'update',
            'id' => 0,
            'txt' => 'updated text',
        ]);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/log.php?action=chronicle', $response->getTargetUrl());
    }

    public function test_chronicle_del_with_zero_id_redirects(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        /** @var LogRepository&MockInterface $logRepository */
        $logRepository = Mockery::mock(LogRepository::class);
        $logRepository->shouldNotReceive('deleteChronicle');
        app()->instance(LogRepository::class, $logRepository);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'POST', [
            'action' => 'chronicle',
            'do' => 'del',
            'id' => 0,
        ]);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/log.php?action=chronicle', $response->getTargetUrl());
    }

    public function test_poll_delete_get_shows_confirmation_form(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'GET', [
            'action' => 'poll',
            'do' => 'delete',
            'pollid' => 1,
        ]);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('form', (string) $response->getContent());
        $this->assertStringContainsString('pollid=1', (string) $response->getContent());
    }

    public function test_poll_delete_post_without_sure_returns_error(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'POST', [
            'action' => 'poll',
            'do' => 'delete',
            'pollid' => 1,
            'sure' => 0,
        ]);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Back off', (string) $response->getContent());
    }

    public function test_poll_delete_with_zero_pollid_returns_error(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'GET', [
            'action' => 'poll',
            'do' => 'delete',
            'pollid' => 0,
        ]);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid poll ID', (string) $response->getContent());
    }

    public function test_poll_delete_get_without_poll_manage_permission_returns_error(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::ADMINISTRATOR->value);

        /** @var ToolRepository&MockInterface $toolRepository */
        $toolRepository = Mockery::mock(ToolRepository::class);
        $toolRepository->shouldReceive('listUserAllPermissions')->andReturn(['log' => true]);
        app()->instance(ToolRepository::class, $toolRepository);

        $controller = app(LogController::class);
        $request = Request::create('/log', 'GET', [
            'action' => 'poll',
            'do' => 'delete',
            'pollid' => 1,
        ]);
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Back off', (string) $response->getContent());
    }

    /**
     * Set up the legacy environment: load lang_functions from the language
     * file into Globals and bind LegacyRedisCache to null so that
     * legacyAbortResponse() can render without Redis.
     */
    private function setupLegacyEnvironment(): void
    {
        $langFile = base_path('lang/en/lang_functions.php');
        if (file_exists($langFile)) {
            $lang_functions = [];
            require $langFile;
            app(Globals::class)->set('lang_functions', $lang_functions);
        }

        $langLogFile = base_path('lang/en/lang_log.php');
        if (file_exists($langLogFile)) {
            $lang_log = [];
            require $langLogFile;
            app(Globals::class)->set('lang_log', $lang_log);
        }

        app()->bind(LegacyRedisCache::class, fn () => null);
    }

    /**
     * Bind a partial mock of CurrentUser that returns the given user array.
     *
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $mock = Mockery::mock($real);
        $mock->shouldReceive('get')->andReturn($user);
        app()->instance(CurrentUser::class, $mock);
    }

    /**
     * Bind a partial mock of CurrentUser with a full user array containing
     * properly typed fields needed by PageLayout::header().
     */
    private function mockCurrentUserWithDefaults(int $userId, int $class): void
    {
        $this->mockCurrentUser([
            'id' => $userId,
            'class' => $class,
            'username' => 'admin',
            'seedbonus' => 0.0,
            'uploaded' => 0,
            'downloaded' => 0,
            'invites' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'enabled' => true,
            'status' => 'confirmed',
            'last_access' => date('Y-m-d H:i:s'),
            'added' => date('Y-m-d H:i:s'),
            'stylesheet' => 1,
            'fontsize' => '',
            'showclienterror' => false,
            'attendance_card' => 0,
            'last_home' => null,
            'passkey' => 'test',
            'auth_key' => 'test',
            'privacy' => 'normal',
            'noad' => false,
            'downloadpos' => true,
            'donor' => false,
            'donoruntil' => null,
            'leechwarn' => false,
            'parked' => false,
            'avatar' => '',
            'title' => '',
            'lang' => 'en',
            'seed_points' => 0,
            'seed_points_per_hour' => 0,
            'page' => 1,
            'support' => false,
            'picker' => false,
            'vip_added' => false,
            'vip_until' => null,
            'clientselect' => '',
            'last_login' => null,
            'last_pm' => null,
            'last_staffmsg' => null,
            'last_comment' => null,
            'last_post' => null,
            'lastwarned' => null,
            'last_browse' => null,
            'last_music' => null,
            'last_catchup' => null,
            'warneduntil' => null,
            'noaduntil' => null,
            'leechwarnuntil' => null,
            'gender' => '',
            'charity' => 0.0,
            'invited_by' => 0,
            'last_offer' => null,
            'forum_access' => null,
            'appendnew' => false,
            'appendpicked' => false,
            'appendsticky' => false,
            'avatars' => true,
            'bmicon' => false,
            'commentpm' => false,
            'deletepms' => false,
            'dlicon' => false,
            'forumpost' => true,
            'savepms' => false,
            'signatures' => true,
            'showcomment' => true,
            'showcomnum' => true,
            'showdescription' => true,
            'showimdb' => true,
            'showlastcom' => true,
            'showlastpost' => true,
            'shownfo' => true,
            'showsmalldescr' => true,
            'uploadpos' => true,
            'timetype' => 'timealive',
            'editsecret' => '',
            'secret' => '',
            'passhash' => '',
            'passhash_algo' => '',
            'email' => 'admin@example.com',
        ]);
    }
}
