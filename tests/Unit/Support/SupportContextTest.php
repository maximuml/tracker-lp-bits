<?php

namespace Tests\Unit\Support;

use App\Support\SupportContext;
use Illuminate\Http\Request;
use Tests\TestCase;

final class SupportContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SupportContext::reset();
    }

    protected function tearDown(): void
    {
        SupportContext::reset();
        parent::tearDown();
    }

    public function test_set_and_get_user(): void
    {
        SupportContext::setUser(['id' => 7]);

        $this->assertSame(['id' => 7], SupportContext::getUser());
    }

    public function test_get_lang_functions_falls_back_to_empty_array(): void
    {
        $this->assertSame([], SupportContext::getLangFunctions());
    }

    public function test_from_request_populates_server_and_cookie(): void
    {
        $request = Request::create('/foo', 'GET', [], ['c_lang_folder' => 'zh'], [], ['HTTP_X_TEST' => 'bar']);

        SupportContext::fromRequest($request);

        $this->assertSame('bar', SupportContext::getServerValue('HTTP_X_TEST'));
        $this->assertSame('zh', SupportContext::getCookieValue('c_lang_folder'));
        $this->assertSame('/foo', SupportContext::getServerValue('REQUEST_URI'));
    }

    public function test_get_query_returns_default_for_missing_key(): void
    {
        $this->assertNull(SupportContext::getQuery('missing'));
        $this->assertSame('default', SupportContext::getQuery('missing', 'default'));
    }
}
