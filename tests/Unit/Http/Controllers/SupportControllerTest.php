<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\SupportController;
use App\Models\User;
use App\Repositories\ToolRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SupportControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupLegacyEnvironment();
        Permissions::resetState();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('complains')->truncate();
        DB::table('complain_replies')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_complains_denies_access_for_guest_on_compose(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'GET');
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_complains_denies_access_for_guest_on_post_new(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'new',
            'email' => 'test@example.com',
            'body' => 'Help',
        ]);
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_complains_denies_access_for_guest_on_post_reply_with_missing_data(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'reply',
            'id' => 0,
            'body' => '',
        ]);
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Missing data', (string) $response->getContent());
    }

    public function test_complains_denies_access_for_guest_on_post_toggle(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'answered',
            'id' => 1,
        ]);
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_complains_denies_access_for_guest_on_post_unknown_action(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'unknown',
        ]);
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_complains_reply_redirects_on_success(): void
    {
        $complainId = $this->insertComplain();

        /** @var User $user */
        $user = User::factory()->create(['class' => 14]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, 14);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'reply',
            'id' => $complainId,
            'body' => 'Reply body',
        ]);
        $request->headers->set('referer', '/complains.php?action=view&id=abc');
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/complains.php', $response->getTargetUrl());
    }

    public function test_complains_reply_returns_error_when_not_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 14]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, 14);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'reply',
            'id' => 999,
            'body' => 'Reply body',
        ]);
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Complain not found', (string) $response->getContent());
    }

    public function test_complains_toggle_redirects_on_success(): void
    {
        $complainId = $this->insertComplain();

        /** @var User $user */
        $user = User::factory()->create(['class' => 14]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, 14);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'answered',
            'id' => $complainId,
        ]);
        $request->headers->set('referer', '/complains.php?action=list');
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/complains.php', $response->getTargetUrl());
    }

    public function test_complains_reply_rejects_missing_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 14]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, 14);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'reply',
            'id' => 0,
            'body' => 'Reply body',
        ]);
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Missing data', (string) $response->getContent());
    }

    public function test_complains_reply_rejects_missing_body(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 14]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, 14);

        $controller = app(SupportController::class);
        $request = Request::create('/complains', 'POST', [
            'action' => 'reply',
            'id' => 5,
            'body' => '',
        ]);
        app()->instance('request', $request);

        $response = $controller->complains($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Missing data', (string) $response->getContent());
    }

    private function insertComplain(string $email = 'test@test.com', string $uuid = 'test-uuid-123'): int
    {
        return (int) DB::table('complains')->insertGetId([
            'uuid' => $uuid,
            'email' => $email,
            'body' => 'Test complain body',
            'added' => now()->toDateTimeString(),
            'answered' => 0,
            'ip' => '127.0.0.1',
        ]);
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
        $repo->shouldReceive('listUserAllPermissions')->andReturn(['staffmem' => true]);
        $repo->shouldReceive('sendMail')->andReturn(null);
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
