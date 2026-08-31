<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TokenController;
use App\Http\Requests\TokenDeleteRequest;
use App\Http\Requests\TokenRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->addToken($request);

        $this->assertNotSame(0, $result['ret']);
    }

    public function test_del_token_returns_fail_when_not_authenticated(): void
    {
        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new TokenController;
        $request = TokenDeleteRequest::create('/api/v1/token/delete', 'DELETE', ['id' => 1]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->delToken($request);

        $this->assertNotSame(0, $result['ret']);
    }

    public function test_del_token_returns_fail_when_validation_fails(): void
    {
        $this->expectException(ValidationException::class);

        $controller = new TokenController;
        $request = TokenDeleteRequest::create('/api/v1/token/delete', 'DELETE', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->delToken($request);
    }
}
