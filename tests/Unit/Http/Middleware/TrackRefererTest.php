<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\TrackReferer;
use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class TrackRefererTest extends TestCase
{
    public function test_referer_host_parses_plain_host(): void
    {
        $request = Request::create('/torrents', 'GET');
        $request->headers->set('referer', 'https://google.com/search?q=linkin+park');

        $this->assertSame('google.com', TrackReferer::refererHost($request));
    }

    public function test_referer_host_lowercases_and_strips_www(): void
    {
        $request = Request::create('/torrents', 'GET');
        $request->headers->set('referer', 'HTTPS://WWW.Reddit.Com/r/LP');

        $this->assertSame('reddit.com', TrackReferer::refererHost($request));
    }

    public function test_referer_host_returns_null_when_missing(): void
    {
        $request = Request::create('/torrents', 'GET');

        $this->assertNull(TrackReferer::refererHost($request));
    }

    public function test_referer_host_returns_null_for_malformed(): void
    {
        $request = Request::create('/torrents', 'GET');
        $request->headers->set('referer', 'not a url');

        $this->assertNull(TrackReferer::refererHost($request));
    }
}
