<?php

namespace Tests\Unit\Support;

use App\Support\Api;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
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

    public function test_call_ignores_legacy_format_envelopes(): void
    {
        // The layui-table/data-table envelopes were removed with the vendor
        // libs — a stray __format param must not change the ret/msg shape.
        $response = Api::call(
            0,
            'OK',
            ['data' => [['id' => 1]], 'meta' => ['total' => 42]],
            ['__format' => 'layui-table', 'draw' => 7]
        );

        $this->assertSame(0, $response['ret']);
        $this->assertSame('OK', $response['msg']);
        $this->assertSame(['data' => [['id' => 1]], 'meta' => ['total' => 42]], $response['data']);
        $this->assertArrayNotHasKey('draw', $response);
        $this->assertArrayNotHasKey('count', $response);
        $this->assertArrayNotHasKey('recordsTotal', $response);
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
