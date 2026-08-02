<?php

namespace Tests\Unit\Support;

use App\Support\Api;
use Tests\TestCase;

class ApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('LOG_LEVEL=error');
    }

    public function test_call_returns_default_envelope(): void
    {
        $response = Api::call(0, 'OK', ['torrent' => 123], []);

        $this->assertSame(0, $response['ret']);
        $this->assertSame('OK', $response['msg']);
        $this->assertSame(['torrent' => 123], $response['data']);
        $this->assertArrayHasKey('time', $response);
        $this->assertArrayHasKey('rid', $response);
    }

    public function test_call_supports_data_table_format(): void
    {
        $response = Api::call(
            0,
            'OK',
            ['data' => [['id' => 1]], 'meta' => ['total' => 42]],
            ['__format' => 'data-table', 'draw' => 7]
        );

        $this->assertSame(0, $response['code']);
        $this->assertSame('OK', $response['msg']);
        $this->assertSame([['id' => 1]], $response['data']);
        $this->assertSame(7, $response['draw']);
        $this->assertSame(42, $response['recordsTotal']);
        $this->assertSame(42, $response['recordsFiltered']);
    }

    public function test_call_supports_layui_table_format(): void
    {
        $response = Api::call(
            0,
            'OK',
            ['data' => [['id' => 2]], 'meta' => ['total' => 5]],
            ['__format' => 'layui-table']
        );

        $this->assertSame(0, $response['code']);
        $this->assertSame('OK', $response['msg']);
        $this->assertSame([['id' => 2]], $response['data']);
        $this->assertSame(5, $response['count']);
        $this->assertArrayNotHasKey('draw', $response);
    }

    public function test_success_and_fail_helpers(): void
    {
        $success = Api::success('Created', ['id' => 99]);
        $this->assertSame(0, $success['ret']);
        $this->assertSame('Created', $success['msg']);
        $this->assertSame(['id' => 99], $success['data']);

        $fail = Api::fail('Bad request', ['error' => 'missing']);
        $this->assertSame(-1, $fail['ret']);
        $this->assertSame('Bad request', $fail['msg']);
        $this->assertSame(['error' => 'missing'], $fail['data']);
    }
}
