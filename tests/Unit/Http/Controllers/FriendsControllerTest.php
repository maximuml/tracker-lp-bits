<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\FriendsController;
use App\Models\User;
use App\Repositories\FriendsRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class FriendsControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupLegacyEnvironment();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_friends_rejects_guest_with_no_id(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'GET');
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid ID', (string) $response->getContent());
    }

    public function test_friends_rejects_guest_with_zero_id(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'GET', ['id' => 0]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid ID', (string) $response->getContent());
    }

    public function test_friends_redirects_guest_with_valid_id_to_friends_php(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'GET', ['id' => 5]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/friends.php', $response->getTargetUrl());
    }

    public function test_friends_add_rejects_get_request(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'GET', ['id' => $userId, 'action' => 'add']);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_friends_delete_rejects_get_request(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'GET', ['id' => $userId, 'action' => 'delete']);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_friends_add_rejects_invalid_targetid(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'POST', [
            'id' => $userId,
            'action' => 'add',
            'targetid' => 0,
            'type' => 'friend',
        ]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid ID', (string) $response->getContent());
    }

    public function test_friends_add_rejects_unknown_type(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'POST', [
            'id' => $userId,
            'action' => 'add',
            'targetid' => 5,
            'type' => 'unknown',
        ]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Unknown type', (string) $response->getContent());
    }

    public function test_friends_add_redirects_on_success(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        /** @var FriendsRepository&MockInterface $repo */
        $repo = Mockery::mock(FriendsRepository::class);
        $repo->shouldReceive('exists')->once()->with($userId, 'friend', 5)->andReturn(false);
        $repo->shouldReceive('add')->once()->with($userId, 'friend', 5);
        app()->instance(FriendsRepository::class, $repo);

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'POST', [
            'id' => $userId,
            'action' => 'add',
            'targetid' => 5,
            'type' => 'friend',
        ]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/friends.php?id='.$userId.'#friends', $response->getTargetUrl());
    }

    public function test_friends_add_rejects_already_existing_friend(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        /** @var FriendsRepository&MockInterface $repo */
        $repo = Mockery::mock(FriendsRepository::class);
        $repo->shouldReceive('exists')->once()->with($userId, 'friend', 5)->andReturn(true);
        app()->instance(FriendsRepository::class, $repo);

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'POST', [
            'id' => $userId,
            'action' => 'add',
            'targetid' => 5,
            'type' => 'friend',
        ]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('already in', (string) $response->getContent());
    }

    public function test_friends_delete_redirects_on_success(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        /** @var FriendsRepository&MockInterface $repo */
        $repo = Mockery::mock(FriendsRepository::class);
        $repo->shouldReceive('delete')->once()->with($userId, 'friend', 5)->andReturn(1);
        app()->instance(FriendsRepository::class, $repo);

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'POST', [
            'id' => $userId,
            'action' => 'delete',
            'targetid' => 5,
            'type' => 'friend',
            'sure' => 1,
        ]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/friends.php?id='.$userId.'#friends', $response->getTargetUrl());
    }

    public function test_friends_delete_returns_error_when_not_found(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        /** @var FriendsRepository&MockInterface $repo */
        $repo = Mockery::mock(FriendsRepository::class);
        $repo->shouldReceive('delete')->once()->with($userId, 'friend', 5)->andReturn(0);
        app()->instance(FriendsRepository::class, $repo);

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'POST', [
            'id' => $userId,
            'action' => 'delete',
            'targetid' => 5,
            'type' => 'friend',
            'sure' => 1,
        ]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Not found', (string) $response->getContent());
    }

    public function test_friends_delete_shows_confirmation_when_not_sure(): void
    {
        $userId = $this->mockCurrentUserWithDefaults();

        $controller = app(FriendsController::class);
        $request = Request::create('/friends', 'POST', [
            'id' => $userId,
            'action' => 'delete',
            'targetid' => 5,
            'type' => 'friend',
            'sure' => 0,
        ]);
        app()->instance('request', $request);

        $response = $controller->friends($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('here if sure', (string) $response->getContent());
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
     * Create a real user in the DB and bind a partial mock of CurrentUser
     * with a full user array containing properly typed fields needed by
     * PageLayout::header(). The real DB user is needed because
     * PageLayout::header() queries MessageRepository which does
     * User::findOrFail($userId). Returns the created user's id.
     */
    private function mockCurrentUserWithDefaults(int $class = 1): int
    {
        /** @var User $user */
        $user = User::factory()->create([
            'class' => $class,
            'enabled' => true,
            'status' => 'confirmed',
        ]);
        $userId = (int) $user->id;

        $this->mockCurrentUser([
            'id' => $userId,
            'class' => $class,
            'username' => 'user',
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
            'email' => 'user@example.com',
        ]);

        return $userId;
    }
}
