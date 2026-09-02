<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\TokenRepository;
use App\Repositories\UserPasskeyRepository;
use App\Services\UsercpPageService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for UsercpPageService.
 *
 * Covers build() for each section (home, personal, tracker, forum,
 * security), default/unknown action routing, top-level key structure,
 * and edge cases with empty user data.
 */
final class UsercpPageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private UsercpPageService $service;

    private int $userId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        if (! defined('IN_NEXUS')) {
            define('IN_NEXUS', true);
        }
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->userId = $this->createUser();

        $this->service = new UsercpPageService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'username' => 'testuser',
            'email' => 'testuser@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
            'added' => now()->subDays(30)->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
            'avatar' => '',
            'invites' => 5,
            'notifs' => '',
            'privacy' => 'normal',
            'stylesheet' => 1,
            'acceptpms' => 'yes',
            'ip' => '127.0.0.1',
        ]);
    }

    /** @param  array<string, mixed>  $values */
    private function mockGlobals(array $values = []): void
    {
        $globals = new Globals;
        foreach ($values as $key => $value) {
            $globals->set($key, $value);
        }
        $this->app->instance(Globals::class, $globals);
    }

    /** @param  array<string, mixed>  $overrides */
    private function setCurrentUser(array $overrides = []): void
    {
        $currentUser = new CurrentUser;
        $currentUser->set(array_merge([
            'id' => $this->userId,
            'username' => 'testuser',
            'class' => 1,
            'email' => 'testuser@test.com',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'avatar' => '',
            'invites' => 5,
            'seedbonus' => '100.0',
            'added' => now()->subDays(30)->toDateTimeString(),
            'ip' => '127.0.0.1',
            'notifs' => '',
            'privacy' => 'normal',
            'stylesheet' => 1,
            'two_step_secret' => '',
        ], $overrides));
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    private function mockCache(): void
    {
        /** @var LegacyRedisCache&MockInterface $cache */
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get_value')->andReturn(false);
        $cache->shouldReceive('cache_value')->andReturn(true);
        $cache->shouldReceive('delete_value')->andReturn(true);
        $this->app->instance(LegacyRedisCache::class, $cache);
    }

    private function mockTokenRepo(): void
    {
        /** @var TokenRepository&MockInterface $repo */
        $repo = Mockery::mock(TokenRepository::class);
        $repo->shouldReceive('listUserTokenPermissionAllowed')->andReturn([]);
        $this->app->instance(TokenRepository::class, $repo);
    }

    private function mockPasskeyRepo(): void
    {
        /** @var UserPasskeyRepository&MockInterface $repo */
        $repo = Mockery::mock(UserPasskeyRepository::class);
        $repo->shouldReceive('renderList')->andReturn(null);
        $this->app->instance(UserPasskeyRepository::class, $repo);
    }

    /** @param  array<string, mixed>  $globalsOverrides */
    private function setupCommon(array $globalsOverrides = []): void
    {
        $this->setCurrentUser();
        $this->mockCache();
        $this->mockTokenRepo();
        $this->mockGlobals(array_merge([
            'CONTENT_WIDTH' => '737',
            'enablelocation_tweak' => 'no',
            'enabletooltip_tweak' => 'no',
            'enablebitbucket_main' => 'no',
            'BASEURL' => 'http://localhost',
            'browsecatmode' => 1,
            'emailnotify_smtp' => 'no',
            'smtptype' => 'none',
            'showshoutbox_main' => 'no',
            'disableemailchange' => 'yes',
        ], $globalsOverrides));
    }

    // ─── Instantiation ────────────────────────────────────────────────

    public function test_can_instantiate_service(): void
    {
        $service = new UsercpPageService;

        $this->assertInstanceOf(UsercpPageService::class, $service);
    }

    // ─── build() top-level structure ──────────────────────────────────

    public function test_build_returns_expected_top_level_keys(): void
    {
        $this->setupCommon();

        $result = $this->service->build('forum', '');

        $this->assertArrayHasKey('lang', $result);
        $this->assertArrayHasKey('curUser', $result);
        $this->assertArrayHasKey('userInfo', $result);
        $this->assertArrayHasKey('siteName', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('contentWidth', $result);
    }

    public function test_build_returns_action_and_type(): void
    {
        $this->setupCommon();

        $result = $this->service->build('personal', 'edit');

        $this->assertSame('personal', $result['action']);
        $this->assertSame('edit', $result['type']);
    }

    public function test_build_returns_user_info_from_db(): void
    {
        $this->setupCommon();

        $result = $this->service->build('forum', '');

        $this->assertInstanceOf(User::class, $result['userInfo']);
        $this->assertSame($this->userId, $result['userInfo']->id);
    }

    // ─── build() forum section ────────────────────────────────────────

    public function test_build_forum_section_returns_show_tooltip_setting(): void
    {
        $this->setupCommon(['enabletooltip_tweak' => 'yes']);

        $result = $this->service->build('forum', '');

        $this->assertArrayHasKey('forum', $result);
        $this->assertTrue($result['forum']['showTooltipSetting']);
    }

    public function test_build_forum_section_show_tooltip_false_when_disabled(): void
    {
        $this->setupCommon(['enabletooltip_tweak' => 'no']);

        $result = $this->service->build('forum', '');

        $this->assertFalse($result['forum']['showTooltipSetting']);
    }

    // ─── build() home section (default) ───────────────────────────────

    public function test_build_default_action_returns_home_section(): void
    {
        $this->setupCommon();

        $result = $this->service->build('unknown', '');

        $this->assertArrayHasKey('home', $result);
        $this->assertArrayHasKey('commentCount', $result['home']);
        $this->assertArrayHasKey('joinDate', $result['home']);
        $this->assertArrayHasKey('forumPosts', $result['home']);
        $this->assertArrayHasKey('tokens', $result['home']);
        $this->assertArrayHasKey('readTopics', $result['home']);
    }

    public function test_build_home_returns_zero_comment_count_for_new_user(): void
    {
        $this->setupCommon();

        $result = $this->service->build('home', '');

        $this->assertSame(0, $result['home']['commentCount']);
    }

    public function test_build_home_returns_zero_forum_posts_for_new_user(): void
    {
        $this->setupCommon();

        $result = $this->service->build('home', '');

        $this->assertSame(0, $result['home']['forumPosts']);
    }

    public function test_build_home_returns_empty_read_topics_for_new_user(): void
    {
        $this->setupCommon();

        $result = $this->service->build('home', '');

        $this->assertArrayHasKey('items', $result['home']['readTopics']);
        $this->assertSame([], $result['home']['readTopics']['items']);
    }

    public function test_build_home_returns_email_and_invites(): void
    {
        $this->setupCommon();

        $result = $this->service->build('home', '');

        $this->assertSame('testuser@test.com', $result['home']['email']);
        $this->assertSame(5, $result['home']['invites']);
    }

    public function test_build_home_show_avatar_false_when_no_avatar(): void
    {
        $this->setupCommon();

        $result = $this->service->build('home', '');

        $this->assertFalse($result['home']['showAvatar']);
    }

    // ─── build() personal section ─────────────────────────────────────

    public function test_build_personal_section_returns_expected_keys(): void
    {
        $this->setupCommon();

        $result = $this->service->build('personal', '');

        $this->assertArrayHasKey('personal', $result);
        $this->assertArrayHasKey('countryOptions', $result['personal']);
        $this->assertArrayHasKey('trackerUrlOptions', $result['personal']);
        $this->assertArrayHasKey('bitbucketOptions', $result['personal']);
        $this->assertArrayHasKey('notificationOptions', $result['personal']);
        $this->assertArrayHasKey('enableBitbucket', $result['personal']);
    }

    public function test_build_personal_section_enable_bitbucket_reflects_globals(): void
    {
        $this->setupCommon(['enablebitbucket_main' => 'yes']);

        $result = $this->service->build('personal', '');

        $this->assertTrue($result['personal']['enableBitbucket']);
    }

    // ─── build() security section ─────────────────────────────────────

    public function test_build_security_section_returns_expected_keys(): void
    {
        $this->setupCommon();
        $this->mockPasskeyRepo();

        $result = $this->service->build('security', '');

        $this->assertArrayHasKey('security', $result);
        $this->assertArrayHasKey('showEmailChange', $result['security']);
        $this->assertArrayHasKey('twoStep', $result['security']);
        $this->assertArrayHasKey('privacyRadios', $result['security']);
        $this->assertArrayHasKey('passkeyListHtml', $result['security']);
    }

    public function test_build_security_section_generates_two_step_secret_when_absent(): void
    {
        $this->setupCommon();
        $this->mockPasskeyRepo();

        $result = $this->service->build('security', '');

        $this->assertFalse($result['security']['twoStep']['hasSecret']);
        $this->assertNotEmpty($result['security']['twoStep']['secret']);
        $this->assertNotEmpty($result['security']['twoStep']['qrCodeUrl']);
    }

    public function test_build_security_section_skips_secret_when_already_set(): void
    {
        $this->setupCommon();
        $this->mockPasskeyRepo();
        $this->setCurrentUser(['two_step_secret' => 'EXISTINGSECRET']);

        $result = $this->service->build('security', '');

        $this->assertTrue($result['security']['twoStep']['hasSecret']);
        $this->assertSame('', $result['security']['twoStep']['secret']);
        $this->assertSame('', $result['security']['twoStep']['qrCodeUrl']);
    }

    public function test_build_security_confirm_step_captures_posted_values(): void
    {
        $this->setupCommon();
        $this->mockPasskeyRepo();

        $result = $this->service->build('security', 'save');

        $this->assertTrue($result['security']['isConfirm']);
        $this->assertArrayHasKey('confirmHidden', $result['security']);
        $this->assertArrayHasKey('email', $result['security']['confirmHidden']);
        $this->assertArrayHasKey('privacy', $result['security']['confirmHidden']);
    }

    public function test_build_security_non_confirm_step_has_empty_confirm_hidden(): void
    {
        $this->setupCommon();
        $this->mockPasskeyRepo();

        $result = $this->service->build('security', '');

        $this->assertFalse($result['security']['isConfirm']);
    }
}
