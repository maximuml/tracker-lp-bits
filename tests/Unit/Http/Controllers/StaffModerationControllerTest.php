<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Enums\UserClass;
use App\Http\Controllers\StaffModerationController;
use App\Models\User;
use App\Repositories\ModtaskRepository;
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

final class StaffModerationControllerTest extends TestCase
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

    public function test_modrules_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modrules', 'GET');
        app()->instance('request', $request);

        $response = $controller->modrules($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Only Administrators and above', (string) $response->getContent());
    }

    public function test_modrules_denies_access_for_non_admin_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::USER->value);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modrules', 'GET');
        app()->instance('request', $request);

        $response = $controller->modrules($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Only Administrators and above', (string) $response->getContent());
    }

    public function test_modrules_del_returns_error_for_get_request(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::ADMINISTRATOR->value);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modrules', 'GET', ['act' => 'del']);
        app()->instance('request', $request);

        $response = $controller->modrules($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_modrules_del_returns_confirmation_when_not_sure(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::ADMINISTRATOR->value);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modrules?act=del', 'POST', ['id' => 1, 'sure' => 0]);
        app()->instance('request', $request);

        $response = $controller->modrules($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('delete a rule', (string) $response->getContent());
    }

    public function test_modrules_addsect_redirects_on_success(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::ADMINISTRATOR->value);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modrules?act=addsect', 'POST', [
            'title' => 'Test Rule',
            'text' => 'Rule content',
            'language' => 1,
        ]);
        app()->instance('request', $request);

        $response = $controller->modrules($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('modrules.php', $response->getTargetUrl());
    }

    public function test_modtask_denies_access_for_non_staff_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::USER->value);

        /** @var ToolRepository&MockInterface $toolRepository */
        $toolRepository = Mockery::mock(ToolRepository::class);
        $toolRepository->shouldReceive('listUserAllPermissions')->andReturn([]);
        app()->instance(ToolRepository::class, $toolRepository);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modtask', 'POST', ['action' => 'edituser']);
        app()->instance('request', $request);

        $response = $controller->modtask($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_modtask_returns_invalid_action_for_staffleader(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modtask', 'POST', ['action' => 'invalid']);
        app()->instance('request', $request);

        $response = $controller->modtask($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid action', (string) $response->getContent());
    }

    public function test_modtask_confirmuser_rejects_invalid_status(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modtask', 'POST', [
            'action' => 'confirmuser',
            'userid' => 1,
            'confirm' => 'invalid',
        ]);
        app()->instance('request', $request);

        $response = $controller->modtask($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid confirmation status', (string) $response->getContent());
    }

    public function test_modtask_confirmuser_redirects_on_success(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        /** @var ModtaskRepository&MockInterface $modtaskRepository */
        $modtaskRepository = Mockery::mock(ModtaskRepository::class);
        $modtaskRepository->shouldReceive('confirmUser')->once()->with(1, 'confirmed');
        app()->instance(ModtaskRepository::class, $modtaskRepository);

        $controller = app(StaffModerationController::class);
        $request = Request::create('/modtask', 'POST', [
            'action' => 'confirmuser',
            'userid' => 1,
            'confirm' => 'confirmed',
        ]);
        app()->instance('request', $request);

        $response = $controller->modtask($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('unco.php', $response->getTargetUrl());
        $this->assertStringContainsString('status=1', $response->getTargetUrl());
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

        app()->bind(LegacyRedisCache::class, fn () => null);

        /** @var ToolRepository&MockInterface $repo */
        $repo = Mockery::mock(ToolRepository::class);
        $repo->shouldReceive('listUserAllPermissions')->andReturn([]);
        app()->instance(ToolRepository::class, $repo);
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
