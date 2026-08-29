<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TokenController;
use App\Http\Requests\TokenRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

final class TokenControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_add_token_returns_fail_when_not_authenticated(): void
    {
        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new TokenController;
        $request = TokenRequest::create('/api/v1/token/add', 'POST', [
            'name' => 'test-token',
            'permissions' => ['torrent.list'],
        ]);

        $result = $controller->addToken($request);

        $this->assertNotSame(0, $result['ret']);
    }

    public function test_del_token_returns_fail_when_not_authenticated(): void
    {
        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new TokenController;
        $request = Request::create('/api/v1/token/delete', 'DELETE', ['id' => 1]);

        $result = $controller->delToken($request);

        $this->assertNotSame(0, $result['ret']);
    }

    public function test_del_token_returns_fail_when_validation_fails(): void
    {
        $controller = new TokenController;
        $request = Request::create('/api/v1/token/delete', 'DELETE', []);

        $result = $controller->delToken($request);

        // delToken catches ValidationException and returns fail
        $this->assertNotSame(0, $result['ret']);
    }
}
