<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\StaffPageController;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

final class StaffPageControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupLegacyEnvironment();
        Permissions::resetState();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staff_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffPageController::class);
        $request = Request::create('/staff', 'GET');
        app()->instance('request', $request);

        $response = $controller->staff($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_staffpanel_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffPageController::class);
        $request = Request::create('/staffpanel', 'GET');
        app()->instance('request', $request);

        $response = $controller->staffpanel($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Access denied', (string) $response->getContent());
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
}
