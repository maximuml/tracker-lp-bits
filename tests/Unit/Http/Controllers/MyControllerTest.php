<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\MyController;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

final class MyControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bonus_redirects_to_mybonus_php_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(MyController::class);
        $request = Request::create('/mybonus', 'GET');
        app()->instance('request', $request);

        $response = $controller->bonus($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/mybonus.php', $response->getTargetUrl());
    }

    public function test_bonus_returns_view_for_authenticated_user(): void
    {
        $this->mockCurrentUser([
            'id' => 1,
            'enabled' => true,
            'username' => 'testuser',
            'seedbonus' => 1000,
            'class' => 1,
        ]);

        $controller = app(MyController::class);
        $request = Request::create('/mybonus', 'GET', ['action' => 'none']);
        app()->instance('request', $request);

        $response = $controller->bonus($request);

        $this->assertInstanceOf(View::class, $response);
    }

    public function test_hr_redirects_to_myhr_php_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(MyController::class);
        $request = Request::create('/myhr', 'GET');
        app()->instance('request', $request);

        $response = $controller->hr($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/myhr.php', $response->getTargetUrl());
    }

    /**
     * Bind a partial mock of CurrentUser that returns the given user array.
     *
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $mock = Mockery::mock($real);
        $mock->shouldReceive('get')->andReturn($user);
        app()->instance(CurrentUser::class, $mock);
    }
}
