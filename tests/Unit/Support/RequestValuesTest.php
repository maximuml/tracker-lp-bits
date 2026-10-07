<?php

namespace Tests\Unit\Support;

use App\Support\NexusContext;
use App\Support\RequestValues;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class RequestValuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        NexusContext::reset();
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('request');
        parent::tearDown();
    }

    public function test_server_value_returns_string_or_default(): void
    {
        app()->instance('request', Request::create('/', 'GET', server: ['X_TEST' => 'abc']));

        $this->assertSame('abc', RequestValues::serverValue('X_TEST'));
        $this->assertSame('dflt', RequestValues::serverValue('X_MISSING', 'dflt'));
    }

    public function test_cookie_value_returns_string_or_default(): void
    {
        app()->instance('request', Request::create('/', 'GET', cookies: ['c1' => 'v1']));

        $this->assertSame('v1', RequestValues::cookieValue('c1'));
        $this->assertNull(RequestValues::cookieValue('missing'));
        $this->assertSame('d', RequestValues::cookieValue('missing', 'd'));
    }
}
