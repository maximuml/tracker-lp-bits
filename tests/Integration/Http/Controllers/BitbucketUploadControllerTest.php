<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Http\Controllers\BitbucketUploadController;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
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
        $this->assertStringContainsString('/web/bitbucket-upload', $response->getTargetUrl());
    }

    public function test_create_redirects_to_login_when_not_authenticated(): void
    {
        $controller = app(BitbucketUploadController::class);
        $request = Request::create('/bitbucket-upload', 'GET');
        app()->instance('request', $request);

        $response = $controller->create($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    public function test_create_returns_view_for_authenticated_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['parked' => false]);
        $this->actingAs($user, 'nexus-web');

        Settings::saveBatch('main', ['enablebitbucket' => 'yes']);
        Settings::resetCache();

        $controller = app(BitbucketUploadController::class);
        $request = Request::create('/bitbucket-upload', 'GET');
        app()->instance('request', $request);

        $response = $controller->create($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('bitbucket.upload', $response->name());
    }

    public function test_store_uploads_multiple_files_and_collects_errors(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['parked' => false]);
        $this->actingAs($user, 'nexus-web');

        Settings::saveBatch('main', ['enablebitbucket' => 'yes']);
        Settings::resetCache();

        $ok1 = UploadedFile::fake()->image('multi_a_'.uniqid().'.png', 100, 100);
        $ok2 = UploadedFile::fake()->image('multi_b_'.uniqid().'.jpg', 100, 100);
        $bad = UploadedFile::fake()->create('multi_bad_'.uniqid().'.txt', 10, 'text/plain');

        $controller = app(BitbucketUploadController::class);
        $request = Request::create('/bitbucket-upload', 'POST', [], [], ['file' => [$ok1, $ok2, $bad]]);
        app()->instance('request', $request);

        $response = $controller->store($request);

        $this->assertInstanceOf(View::class, $response);
        $data = $response->getData();
        $this->assertCount(2, $data['results']);
        $this->assertCount(1, $data['errors']);
        $this->assertSame($bad->getClientOriginalName(), $data['errors'][0]['filename']);

        foreach ($data['results'] as $result) {
            $path = public_path('bitbucket/'.$result['filename']);
            $this->assertFileExists($path);
            @unlink($path);
        }
    }
}
