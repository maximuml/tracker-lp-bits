<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UtilityController;
use App\Repositories\UserPasskeyRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class UtilityControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ajax_redirects_to_ajax_php_when_redis_cache_is_null(): void
    {
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(UtilityController::class);
        $request = Request::create('/ajax', 'GET', ['action' => 'getPasskeyGetArgs']);
        app()->instance('request', $request);

        $response = $controller->ajax($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/ajax.php', $response->getTargetUrl());
    }

    public function test_ajax_returns_error_for_invalid_action(): void
    {
        $this->mockLegacyRedisCache();
        $this->mockCurrentUser(['id' => 1, 'enabled' => true, 'username' => 'testuser']);

        $controller = app(UtilityController::class);
        $request = Request::create('/ajax', 'GET', ['action' => 'invalidAction']);
        app()->instance('request', $request);

        $response = $controller->ajax($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertSame(1, $data['ret']);
        $this->assertStringContainsString('Invalid action', $data['msg']);
    }

    public function test_ajax_returns_success_for_valid_action(): void
    {
        $this->mockLegacyRedisCache();
        $this->mockCurrentUser(['id' => 1, 'enabled' => true, 'username' => 'testuser']);

        $mockPasskeyRepo = Mockery::mock(UserPasskeyRepository::class);
        $mockPasskeyRepo->shouldReceive('getGetArgs')->once()->andReturn(['challenge' => 'test-challenge']);
        app()->instance(UserPasskeyRepository::class, $mockPasskeyRepo);

        $controller = app(UtilityController::class);
        $request = Request::create('/ajax', 'GET', ['action' => 'getPasskeyGetArgs']);
        app()->instance('request', $request);

        $response = $controller->ajax($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertSame(0, $data['ret']);
        $this->assertSame(['challenge' => 'test-challenge'], $data['data']);
    }

    public function test_ajax_returns_error_for_exception(): void
    {
        $this->mockLegacyRedisCache();
        $this->mockCurrentUser(['id' => 1, 'enabled' => true, 'username' => 'testuser']);

        $mockPasskeyRepo = Mockery::mock(UserPasskeyRepository::class);
        $mockPasskeyRepo->shouldReceive('getGetArgs')->once()->andThrow(new \RuntimeException('Test error'));
        app()->instance(UserPasskeyRepository::class, $mockPasskeyRepo);

        $controller = app(UtilityController::class);
        $request = Request::create('/ajax', 'GET', ['action' => 'getPasskeyGetArgs']);
        app()->instance('request', $request);

        $response = $controller->ajax($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertSame(-1, $data['ret']);
        $this->assertSame('Test error', $data['msg']);
    }

    public function test_search_redirects_guests_to_search_php(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(UtilityController::class);
        $request = Request::create('/search', 'GET', ['q' => 'x']);
        app()->instance('request', $request);

        $response = $controller->search($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/search.php?q=x', $response->getTargetUrl());
    }

    public function test_getattachment_rejects_missing_id_or_key(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(UtilityController::class);
        $request = Request::create('/getattachment', 'GET');
        app()->instance('request', $request);

        $response = $controller->getattachment($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid id or key.', $response->getContent());
    }

    public function test_getattachment_returns_404_for_unknown_row(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(UtilityController::class);
        $request = Request::create('/getattachment', 'GET', ['id' => 999999, 'dlkey' => 'nope']);
        app()->instance('request', $request);

        $response = $controller->getattachment($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('No attachment found.', $response->getContent());
    }

    public function test_image_rejects_wrong_captcha_action(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(UtilityController::class);
        $request = Request::create('/image', 'GET', ['action' => 'bogus']);
        app()->instance('request', $request);

        $response = $controller->image($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Invalid captcha action', $response->getContent());
    }

    public function test_page_renders_raw_legacy_page(): void
    {
        $this->mockCurrentUser(null);
        View::shouldReceive('make')->once()->with('page.index', [])->andReturn($this->fakeView('page html'));

        $controller = app(UtilityController::class);
        $request = Request::create('/page', 'GET');
        app()->instance('request', $request);

        $response = $controller->page($request);

        $this->assertSame('page html', $response->getContent());
    }

    public function test_tags_passes_post_data_to_view(): void
    {
        $this->mockCurrentUser(null);
        View::shouldReceive('make')->once()->with('tags.index', ['test' => 'abc'])->andReturn($this->fakeView());

        $controller = app(UtilityController::class);
        $request = Request::create('/tags', 'POST', ['test' => 'abc']);
        app()->instance('request', $request);

        $response = $controller->tags($request);

        $this->assertInstanceOf(ViewInstance::class, $response);
    }

    public function test_suggest_returns_empty_body_for_empty_query(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(UtilityController::class);
        $request = Request::create('/searchsuggest', 'GET');
        app()->instance('request', $request);

        $response = $controller->suggest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('text/xml; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function test_suggest_returns_matching_keywords(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(UtilityController::class);
        $request = Request::create('/searchsuggest', 'GET', ['q' => 'zzz-no-match']);
        app()->instance('request', $request);

        $response = $controller->suggest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_preview_renders_legacy_page(): void
    {
        $this->mockCurrentUser(['id' => 1]);
        View::shouldReceive('make')->once()->with('preview.index', ['body' => 'hello'])->andReturn($this->fakeView());

        $controller = app(UtilityController::class);
        $request = Request::create('/preview', 'POST', ['body' => 'hello']);
        app()->instance('request', $request);

        $response = $controller->preview($request);

        $this->assertInstanceOf(ViewInstance::class, $response);
    }

    public function test_moresmilies_renders_legacy_page(): void
    {
        $this->mockCurrentUser(['id' => 1]);
        View::shouldReceive('make')->once()->with('moresmilies.index', [])->andReturn($this->fakeView());

        $controller = app(UtilityController::class);
        $request = Request::create('/moresmilies', 'GET');
        app()->instance('request', $request);

        $response = $controller->moresmilies($request);

        $this->assertInstanceOf(ViewInstance::class, $response);
    }

    public function test_smilies_renders_legacy_page(): void
    {
        $this->mockCurrentUser(['id' => 1]);
        View::shouldReceive('make')->once()->with('smilies.index', [])->andReturn($this->fakeView());

        $controller = app(UtilityController::class);
        $request = Request::create('/smilies', 'GET');
        app()->instance('request', $request);

        $response = $controller->smilies($request);

        $this->assertInstanceOf(ViewInstance::class, $response);
    }

    public function test_opensearch_returns_xml_description(): void
    {
        $this->mockCurrentUser(null);
        Cache::forget('opensearch_description');

        $controller = app(UtilityController::class);
        $request = Request::create('/opensearch', 'GET');
        app()->instance('request', $request);

        $response = $controller->opensearch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('OpenSearchDescription', (string) $response->getContent());
    }

    public function test_confirmemail_aborts_on_malformed_path(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(UtilityController::class);
        $request = Request::create('/confirmemail', 'GET');
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);
        $controller->confirmemail($request);
    }

    public function test_confirmemail_aborts_on_invalid_email(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(UtilityController::class);
        $request = Request::create('/confirmemail/1/'.str_repeat('a', 32).'/not-an-email', 'GET');
        $request->setRouteResolver(fn () => new class
        {
            public function parameter(string $name): ?string
            {
                return '1/'.str_repeat('a', 32).'/not-an-email';
            }
        });
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);
        $controller->confirmemail($request);
    }

    public function test_ok_renders_legacy_page_with_signup_type(): void
    {
        $this->mockCurrentUser(null);
        View::shouldReceive('make')->once()->andReturn($this->fakeView());

        $controller = app(UtilityController::class);
        $request = Request::create('/ok', 'GET', ['type' => 'signup', 'email' => 'a@b.c']);
        app()->instance('request', $request);

        $response = $controller->ok($request);

        $this->assertInstanceOf(ViewInstance::class, $response);
    }

    private function fakeView(string $content = 'html'): ViewInstance
    {
        /** @var ViewInstance&MockInterface $view */
        $view = Mockery::mock(ViewInstance::class);
        $view->shouldReceive('render')->andReturn($content);

        return $view;
    }

    /**
     * Bind a mock LegacyRedisCache so the controller's app() resolution
     * returns a non-null cache without connecting to Redis.
     */
    private function mockLegacyRedisCache(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        app()->instance(LegacyRedisCache::class, $cache);
    }

    /**
     * Bind a real CurrentUser instance with the given user array.
     *
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $real->set($user);
        app()->instance(CurrentUser::class, $real);
    }
}
