<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Enums\MedalGetType;
use App\Http\Controllers\BonusShopController;
use App\Models\Medal;
use App\Models\User;
use App\Support\AssetAppender;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class BonusShopControllerTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_medal_sanitizes_description_and_serializes_confirm_message(): void
    {
        $user = User::factory()->create();
        $this->mockCurrentUser(['id' => (int) $user->id, 'seedbonus' => 0.0]);

        Medal::query()->create([
            'name' => 'Test medal',
            'get_type' => MedalGetType::EXCHANGE->value,
            'description' => 'ok <b>bold</b><script>alert(1)</script><img src="x" onerror="alert(2)">',
            'price' => 0,
            'display_on_medal_page' => 1,
            'priority' => 1,
        ]);

        app('translator')->addLines([
            'medal.confirm_to_buy' => 'Sure?"],top.evil=1,//',
            'medal.confirm_to_gift' => 'Gift?',
        ], 'en');

        $controller = app(BonusShopController::class);
        $request = Request::create('/medal', 'GET');
        app()->instance('request', $request);

        $view = $controller->medal($request);
        $this->assertInstanceOf(View::class, $view);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $view->getData()['rows'];
        $description = (string) $rows[0]['description'];
        $this->assertStringContainsString('<b>bold</b>', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onerror', $description);

        $footerJs = implode('', AssetAppender::getAppendFootersSafe());
        $this->assertStringContainsString('Sure?\\u0022],top.evil=1,\\/\\/', $footerJs);
        $this->assertStringNotContainsString('"],top.evil', $footerJs);
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
