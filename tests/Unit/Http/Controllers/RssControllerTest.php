<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Enums\UserClass;
use App\Http\Controllers\RssController;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

final class RssControllerTest extends TestCase
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

    public function test_getrss_redirects_guest_to_getrss_php_on_get(): void
    {
        $this->mockCurrentUser(null);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(RssController::class);
        $request = Request::create('/getrss', 'GET');
        app()->instance('request', $request);

        $response = $controller->getrss($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/getrss.php', $response->getTargetUrl());
    }

    public function test_getrss_redirects_guest_to_getrss_php_on_post(): void
    {
        $this->mockCurrentUser(null);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(RssController::class);
        $request = Request::create('/getrss', 'POST', [
            'showrows' => '10',
        ]);
        app()->instance('request', $request);

        $response = $controller->getrss($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/getrss.php', $response->getTargetUrl());
    }

    public function test_getrss_post_returns_error_for_invalid_showrows(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user);
        app()->bind(LegacyRedisCache::class, fn () => null);
        app(Globals::class)->set('BASEURL', 'http://localhost');
        app(Globals::class)->set('browsecatmode', 1);

        $controller = app(RssController::class);
        $request = Request::create('/getrss', 'POST', [
            'showrows' => '999',
        ]);
        app()->instance('request', $request);

        $response = $controller->getrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('No row', (string) $response->getContent());
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
    private function mockCurrentUserWithDefaults(User $user): void
    {
        $this->mockCurrentUser([
            'id' => $user->id,
            'class' => $user->class,
            'username' => $user->username,
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
            'passkey' => $user->passkey,
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
    }

    /**
     * Set up the legacy environment: load lang_functions from the language
     * file into Globals and bind LegacyRedisCache to null so that
     * legacyAbortResponse() and Html::stdhead() can render without Redis.
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
}
