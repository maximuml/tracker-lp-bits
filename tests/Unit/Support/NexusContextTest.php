<?php

namespace Tests\Unit\Support;

use App\Support\NexusContext;
use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class NexusContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        NexusContext::reset();
    }

    protected function tearDown(): void
    {
        NexusContext::reset();
        parent::tearDown();
    }

    public function test_instance_returns_shared_context(): void
    {
        $this->assertSame(NexusContext::instance(), NexusContext::instance());
    }

    public function test_set_and_get_user(): void
    {
        NexusContext::instance()->setUser(['id' => 7]);

        $this->assertSame(['id' => 7], NexusContext::instance()->getUser());
    }

    public function test_set_from_request_populates_server_and_cookie(): void
    {
        $request = Request::create('/foo', 'GET', [], ['c_lang_folder' => 'zh'], [], ['HTTP_X_TEST' => 'bar']);

        NexusContext::instance()->setFromRequest($request);

        $this->assertSame('bar', NexusContext::instance()->getServerValue('HTTP_X_TEST'));
        $this->assertSame('zh', NexusContext::instance()->getCookieValue('c_lang_folder'));
        $this->assertSame('/foo', NexusContext::instance()->getServerValue('REQUEST_URI'));
    }

    public function test_get_query_returns_default_for_missing_key(): void
    {
        $this->assertNull(NexusContext::instance()->getQuery('missing'));
        $this->assertSame('default', NexusContext::instance()->getQuery('missing', 'default'));
    }
}
