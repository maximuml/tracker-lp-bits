<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\BonusShopController;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

final class BonusShopControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalLang();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_freeleech_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(BonusShopController::class);
        $request = Request::create('/freeleech', 'GET');
        app()->instance('request', $request);

        $response = $controller->freeleech($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Access denied', (string) $response->getContent());
    }

    public function test_freeleech_denies_state_change_via_get_request_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(BonusShopController::class);
        $request = Request::create('/freeleech', 'GET', ['action' => 'setallfree']);
        app()->instance('request', $request);

        $response = $controller->freeleech($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Access denied', (string) $response->getContent());
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

    /**
     * Set up minimal language strings so legacyAbortResponse's stdhead()
     * can render for guest users (no authenticated user block).
     */
    private function setupMinimalLang(): void
    {
        app(Globals::class)->set('lang_functions', [
            'text_login' => 'Login',
            'text_signup' => 'Signup',
        ]);
    }
}
