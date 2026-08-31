<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\RejectGetMutations;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class RejectGetMutationsTest extends TestCase
{
    public function test_get_returns_405(): void
    {
        $middleware = new RejectGetMutations;
        $request = Request::create('/ajax', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST', $response->headers->get('Allow'));
    }

    public function test_head_returns_405(): void
    {
        $middleware = new RejectGetMutations;
        $request = Request::create('/ajax', 'HEAD');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(405, $response->getStatusCode());
    }

    public function test_post_passes_through(): void
    {
        $middleware = new RejectGetMutations;
        $request = Request::create('/ajax', 'POST');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_json_get_returns_json_error(): void
    {
        $middleware = new RejectGetMutations;
        $request = Request::create('/ajax', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertStringContainsString('method_not_allowed', $response->getContent());
    }
}
