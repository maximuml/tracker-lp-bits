<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Enums\ReportType;
use App\Enums\UserClass;
use App\Http\Controllers\ModerationController;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ModerationRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class ModerationControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Permissions::resetState();
        Cache::flush();
        $this->setupMinimalLang();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_report_returns_invalid_action_for_guest_without_params(): void
    {
        $this->mockCurrentUser(null);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(ModerationController::class);
        $request = Request::create('/report', 'GET');
        app()->instance('request', $request);

        $response = $controller->report($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid action', (string) $response->getContent());
    }

    public function test_report_returns_missing_reason_when_guest_posts_without_reason(): void
    {
        $this->mockCurrentUser(null);
        app()->bind(LegacyRedisCache::class, fn () => null);

        /** @var ModerationRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ModerationRepository::class);
        $repository->shouldNotReceive('createReport');
        app()->instance(ModerationRepository::class, $repository);

        $controller = app(ModerationController::class);
        $request = Request::create('/report', 'POST', ['takeuser' => 5]);
        app()->instance('request', $request);

        $response = $controller->report($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Missing reason', (string) $response->getContent());
    }

    public function test_report_returns_invalid_action_for_guest_with_invalid_params(): void
    {
        $this->mockCurrentUser(null);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(ModerationController::class);
        $request = Request::create('/report', 'GET', ['user' => 0, 'torrent' => 0]);
        app()->instance('request', $request);

        $response = $controller->report($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid action', (string) $response->getContent());
    }

    public function test_reports_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(ModerationController::class);
        $request = Request::create('/reports', 'GET');
        app()->instance('request', $request);

        $response = $controller->reports($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_reports_renders_torrent_report_for_int_backed_type(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::ADMINISTRATOR->value);

        $torrent = Torrent::factory()->create(['name' => 'EnumCheckTorrent']);

        /** @var ModerationRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ModerationRepository::class);
        $repository->shouldReceive('countReports')->once()->andReturn(1);
        $repository->shouldReceive('getReports')->once()->andReturn([
            [
                'id' => 1,
                'type' => ReportType::TORRENT->value,
                'reportid' => $torrent->id,
                'addedby' => $user->id,
                'added' => date('Y-m-d H:i:s'),
                'dealtwith' => 0,
                'dealtby' => 0,
                'reason' => 'test enum reason',
            ],
        ]);
        app()->instance(ModerationRepository::class, $repository);

        $controller = app(ModerationController::class);
        $request = Request::create('/reports', 'GET');
        app()->instance('request', $request);

        $response = $controller->reports($request);

        $this->assertInstanceOf(View::class, $response);
        $rows = $response->getData()['rows'];
        $this->assertSame('Torrent', $rows[0]['type_label']);
        $this->assertStringContainsString('details.php?id='.$torrent->id, $rows[0]['reporting']);
        $this->assertStringContainsString('EnumCheckTorrent', $rows[0]['reporting']);
        $this->assertSame('test enum reason', $rows[0]['reason']);
    }

    public function test_reports_renders_user_report_for_int_backed_type(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::ADMINISTRATOR->value);

        /** @var User $reported */
        $reported = User::factory()->create();

        /** @var ModerationRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ModerationRepository::class);
        $repository->shouldReceive('countReports')->once()->andReturn(1);
        $repository->shouldReceive('getReports')->once()->andReturn([
            [
                'id' => 2,
                'type' => ReportType::USER->value,
                'reportid' => $reported->id,
                'addedby' => $user->id,
                'added' => date('Y-m-d H:i:s'),
                'dealtwith' => 0,
                'dealtby' => 0,
                'reason' => 'user report reason',
            ],
        ]);
        app()->instance(ModerationRepository::class, $repository);

        $controller = app(ModerationController::class);
        $request = Request::create('/reports', 'GET');
        app()->instance('request', $request);

        $response = $controller->reports($request);

        $this->assertInstanceOf(View::class, $response);
        $rows = $response->getData()['rows'];
        $this->assertSame('User', $rows[0]['type_label']);
        $this->assertStringContainsString((string) $reported->username, $rows[0]['reporting']);
        $this->assertSame('user report reason', $rows[0]['reason']);
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
     * Mock CurrentUser with a realistic array so staff checks and the page
     * layout have all the properly typed fields they need.
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
            'status' => 1,
            'last_access' => date('Y-m-d H:i:s'),
            'added' => date('Y-m-d H:i:s'),
            'stylesheet' => 1,
            'fontsize' => '',
            'showclienterror' => false,
            'attendance_card' => 0,
            'last_home' => null,
            'passkey' => 'test',
            'auth_key' => 'test',
            'privacy' => 1,
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
        ]);
    }

    /**
     * Set up minimal language strings so legacyAbortResponse's stdhead()
     * can render for guest users (no authenticated user block).
     */
    private function setupMinimalLang(): void
    {
        app(Globals::class)->set('lang_functions', [
            'text_login' => 'Login',
            'text_signup' => 'Signup',
        ]);
    }
}
