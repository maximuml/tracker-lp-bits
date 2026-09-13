<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Support\CurrentUser;
use App\Support\LegacyHeaderBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;
use Tests\Unit\Http\Controllers\Fixtures\TestLegacyController;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class LegacyControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        app(CurrentUser::class)->set(null);
        Mockery::close();
        parent::tearDown();
    }

    private function controller(): TestLegacyController
    {
        return new TestLegacyController;
    }

    private function fakeView(string $content = 'html'): ViewInstance
    {
        /** @var ViewInstance&MockInterface $view */
        $view = Mockery::mock(ViewInstance::class);
        $view->shouldReceive('render')->andReturn($content);

        return $view;
    }

    public function test_legacy_page_redirects_guests_to_php_url(): void
    {
        $response = $this->controller()->page(Request::create('/mypage.php?a=1', 'GET'), 'mypage');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/mypage.php?a=1', $response->getTargetUrl());
    }

    public function test_legacy_page_redirects_guests_without_query_string(): void
    {
        $response = $this->controller()->page(Request::create('/mypage.php', 'GET'), 'mypage');

        $this->assertSame('http://localhost/mypage.php', $response->getTargetUrl());
    }

    public function test_legacy_page_returns_view_for_public_page(): void
    {
        $view = $this->fakeView();
        View::shouldReceive('make')->once()->with('mypage.index', ['k' => 'v'])->andReturn($view);

        $response = $this->controller()->page(Request::create('/mypage.php', 'GET'), 'mypage', false, ['k' => 'v']);

        $this->assertSame($view, $response);
    }

    public function test_legacy_page_with_redirect_redirects_guests(): void
    {
        $response = $this->controller()->pageWithRedirect(Request::create('/mypage.php?b=2', 'GET'), 'mypage');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/mypage.php?b=2', $response->getTargetUrl());
    }

    public function test_legacy_page_with_redirect_returns_rendered_content(): void
    {
        View::shouldReceive('make')->once()->andReturn($this->fakeView('rendered body'));

        $response = $this->controller()->pageWithRedirect(Request::create('/mypage.php', 'GET'), 'mypage', false);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('rendered body', $response->getContent());
    }

    public function test_legacy_page_with_redirect_follows_location_header(): void
    {
        View::shouldReceive('make')->once()->andReturn($this->fakeView('unused'));
        app(LegacyHeaderBag::class)->set('Location', '/target.php');

        $response = $this->controller()->pageWithRedirect(Request::create('/mypage.php', 'GET'), 'mypage', false);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/target.php', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_legacy_page_with_redirect_preserves_3xx_status(): void
    {
        View::shouldReceive('make')->once()->andReturn($this->fakeView('unused'));
        $headerBag = app(LegacyHeaderBag::class);
        $headerBag->set('Location', '/target.php');
        $headerBag->setStatusCode(301);

        $response = $this->controller()->pageWithRedirect(Request::create('/mypage.php', 'GET'), 'mypage', false);

        $this->assertSame(301, $response->getStatusCode());
        $headerBag->remove('Location');
        $headerBag->flush();
    }

    public function test_legacy_page_raw_redirects_guests(): void
    {
        $response = $this->controller()->pageRaw(Request::create('/mypage.php?c=3', 'GET'), 'mypage');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/mypage.php?c=3', $response->getTargetUrl());
    }

    public function test_legacy_page_raw_returns_content_with_headers(): void
    {
        View::shouldReceive('make')->once()->andReturn($this->fakeView('raw body'));
        $headerBag = app(LegacyHeaderBag::class);
        $headerBag->set('X-Legacy', 'yes');
        $headerBag->setStatusCode(201);

        $response = $this->controller()->pageRaw(Request::create('/mypage.php', 'GET'), 'mypage', false);

        $this->assertSame('raw body', $response->getContent());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('yes', $response->headers->get('X-Legacy'));
    }

    public function test_legacy_page_raw_defaults_status_to_200(): void
    {
        View::shouldReceive('make')->once()->andReturn($this->fakeView('body'));

        $response = $this->controller()->pageRaw(Request::create('/mypage.php', 'GET'), 'mypage', false);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_legacy_page_raw_follows_location_header(): void
    {
        View::shouldReceive('make')->once()->andReturn($this->fakeView('unused'));
        app(LegacyHeaderBag::class)->set('Location', '/elsewhere.php');

        $response = $this->controller()->pageRaw(Request::create('/mypage.php', 'GET'), 'mypage', false);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/elsewhere.php', $response->getTargetUrl());
    }

    public function test_legacy_abort_response_returns_rendered_error_page(): void
    {
        $response = $this->controller()->abortPage('Access Denied', 'you shall not pass');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('you shall not pass', (string) $response->getContent());
    }

    public function test_legacy_page_renders_view_for_authenticated_user(): void
    {
        app(CurrentUser::class)->set(['id' => 1]);
        $view = $this->fakeView();
        View::shouldReceive('make')->once()->with('mypage.index', [])->andReturn($view);

        $response = $this->controller()->page(Request::create('/mypage.php', 'GET'), 'mypage', true);

        $this->assertSame($view, $response);
    }
}
