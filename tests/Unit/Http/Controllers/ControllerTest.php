<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\TestCase;
use Tests\Unit\Http\Controllers\Fixtures\TestController;

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

    public function test_pagination_parameters_use_limit_and_page(): void
    {
        app()->instance('request', Request::create('/x?limit=25&page=3', 'GET'));

        $this->assertSame(['25', ['*'], 'page', '3'], $this->controller()->pagination());
    }

    public function test_pagination_parameters_support_data_table_format(): void
    {
        app()->instance('request', Request::create('/x?__format=data-table&length=50&start=100', 'GET'));

        $this->assertSame(['50', ['*'], 'page', 3], $this->controller()->pagination());
    }

    public function test_has_extra_field_reads_request_csv(): void
    {
        app()->instance('request', Request::create('/x?extra_fields=a,b', 'GET'));

        $controller = $this->controller();
        $this->assertTrue($controller->extraField('a'));
        $this->assertTrue($controller->extraField('b'));
        $this->assertFalse($controller->extraField('z'));
    }

    public function test_append_extra_settings_only_adds_requested_names(): void
    {
        app()->instance('request', Request::create('/x?extra_settings=main.sitename', 'GET'));

        $additional = ['existing' => 1];
        $this->controller()->extraSettings($additional, ['main.sitename', 'nonexistent.setting']);

        $this->assertSame(1, $additional['existing']);
        $this->assertArrayHasKey('extra_settings', $additional);
        $this->assertArrayHasKey('main.sitename', $additional['extra_settings']);
        $this->assertArrayNotHasKey('nonexistent.setting', $additional['extra_settings']);
    }

    public function test_append_extra_settings_leaves_additional_untouched_when_nothing_requested(): void
    {
        app()->instance('request', Request::create('/x', 'GET'));

        $additional = ['existing' => 1];
        $this->controller()->extraSettings($additional, ['main.sitename']);

        $this->assertSame(['existing' => 1], $additional);
    }
}
