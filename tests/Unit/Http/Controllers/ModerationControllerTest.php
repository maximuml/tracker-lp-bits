<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ModerationController;
use App\Repositories\ModerationRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

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
