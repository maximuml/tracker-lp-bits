<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\BitbucketUploadController;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Globals;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

final class BitbucketUploadControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_create_redirects_to_legacy_when_redis_cache_unavailable(): void
    {
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(BitbucketUploadController::class);
        $request = Request::create('/bitbucket-upload', 'GET', ['foo' => 'bar']);
        app()->instance('request', $request);

        $response = $controller->create($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/bitbucket-upload.php', $response->getTargetUrl());
    }

    public function test_create_redirects_to_login_when_not_authenticated(): void
    {
        $controller = app(BitbucketUploadController::class);
        $request = Request::create('/bitbucket-upload', 'GET');
        app()->instance('request', $request);

        $response = $controller->create($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/login.php', $response->getTargetUrl());
    }

    public function test_create_returns_view_for_authenticated_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['parked' => false]);
        $this->actingAs($user, 'nexus-web');

        app(Globals::class)->set('enablebitbucket_main', 'yes');

        $controller = app(BitbucketUploadController::class);
        $request = Request::create('/bitbucket-upload', 'GET');
        app()->instance('request', $request);

        $response = $controller->create($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('bitbucket.upload', $response->name());
    }
}
