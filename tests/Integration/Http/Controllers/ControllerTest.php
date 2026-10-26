<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\Integration\Http\Controllers\Fixtures\TestController;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ControllerTest extends TestCase
{
    private function controller(): TestController
    {
        return new TestController;
    }

    public function test_success_returns_api_envelope(): void
    {
        app()->instance('request', Request::create('/x', 'GET'));

        $result = $this->controller()->success(['x' => 1], 'done');

        $this->assertSame(0, $result['ret']);
        $this->assertSame('done', $result['msg']);
        $this->assertSame(['x' => 1], $result['data']);
    }

    public function test_success_derives_message_from_caller(): void
    {
        app()->instance('request', Request::create('/x', 'GET'));

        $result = $this->controller()->success(['x' => 1]);

        $this->assertSame(0, $result['ret']);
        $this->assertIsString($result['msg']);
        $this->assertNotSame('', $result['msg']);
    }

    public function test_fail_returns_api_envelope(): void
    {
        app()->instance('request', Request::create('/x', 'GET'));

        $result = $this->controller()->fail(['y' => 2], 'nope');

        $this->assertSame('nope', $result['msg']);
        $this->assertSame(['y' => 2], $result['data']);
        $this->assertNotSame(0, $result['ret']);
    }

    public function test_has_extra_field_reads_request_csv(): void
    {
        $request = Request::create('/x?extra_fields=a,b', 'GET');
        app()->instance('request', $request);

        $controller = $this->controller();
        $this->assertTrue($controller->extraField($request, 'a'));
        $this->assertTrue($controller->extraField($request, 'b'));
        $this->assertFalse($controller->extraField($request, 'z'));
    }

    public function test_append_extra_settings_only_adds_requested_names(): void
    {
        $request = Request::create('/x?extra_settings=main.sitename', 'GET');
        app()->instance('request', $request);

        $additional = ['existing' => 1];
        $this->controller()->extraSettings($request, $additional, ['main.sitename', 'nonexistent.setting']);

        $this->assertSame(1, $additional['existing']);
        $this->assertArrayHasKey('extra_settings', $additional);
        $this->assertArrayHasKey('main.sitename', $additional['extra_settings']);
        $this->assertArrayNotHasKey('nonexistent.setting', $additional['extra_settings']);
    }

    public function test_append_extra_settings_leaves_additional_untouched_when_nothing_requested(): void
    {
        $request = Request::create('/x', 'GET');
        app()->instance('request', $request);

        $additional = ['existing' => 1];
        $this->controller()->extraSettings($request, $additional, ['main.sitename']);

        $this->assertSame(['existing' => 1], $additional);
    }
}
