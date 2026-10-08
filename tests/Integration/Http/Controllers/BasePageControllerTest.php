<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Integration\Http\Controllers\Fixtures\TestBasePageController;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BasePageControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        app(CurrentUser::class)->set(null);
        Mockery::close();
        parent::tearDown();
    }

    private function controller(): TestBasePageController
    {
        return new TestBasePageController;
    }

    private function fakeView(string $content = 'html'): ViewInstance
    {
        /** @var ViewInstance&MockInterface $view */
        $view = Mockery::mock(ViewInstance::class);
        $view->shouldReceive('render')->andReturn($content);

        return $view;
    }

    public function test_render_page_redirects_guests_to_php_url(): void
    {
        $response = $this->controller()->page(Request::create('/mypage?a=1', 'GET'), 'mypage');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/mypage?a=1', $response->getTargetUrl());
    }

    public function test_render_page_redirects_guests_without_query_string(): void
    {
        $response = $this->controller()->page(Request::create('/mypage', 'GET'), 'mypage');

        $this->assertSame('http://localhost/mypage', $response->getTargetUrl());
    }

    public function test_render_page_returns_view_for_public_page(): void
    {
        $view = $this->fakeView();
        View::shouldReceive('make')->once()->with('mypage.index', ['k' => 'v'])->andReturn($view);

        $response = $this->controller()->page(Request::create('/mypage', 'GET'), 'mypage', false, ['k' => 'v']);

        $this->assertSame($view, $response);
    }

    public function test_abort_response_returns_rendered_error_page(): void
    {
        $response = $this->controller()->abortPage('Access Denied', 'you shall not pass');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('you shall not pass', (string) $response->getContent());
    }

    public function test_render_page_renders_view_for_authenticated_user(): void
    {
        app(CurrentUser::class)->set(['id' => 1]);
        $view = $this->fakeView();
        View::shouldReceive('make')->once()->with('mypage.index', [])->andReturn($view);

        $response = $this->controller()->page(Request::create('/mypage', 'GET'), 'mypage', true);

        $this->assertSame($view, $response);
    }
}
