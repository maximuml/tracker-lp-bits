<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\StaffMessageController;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

final class StaffMessageControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupLegacyEnvironment();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staffmess_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/staffmess', 'GET');
        app()->instance('request', $request);

        $response = $controller->staffmess($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Access denied', (string) $response->getContent());
    }

    public function test_take_staffmess_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/staffmess', 'POST');
        app()->instance('request', $request);

        $response = $controller->takeStaffmess($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_contactstaff_redirects_guest_to_contactstaff_php(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/contactstaff', 'GET');
        app()->instance('request', $request);

        $response = $controller->contactstaff($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/contactstaff.php', $response->getTargetUrl());
    }

    public function test_contactstaff_redirects_guest_preserving_query_string(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/contactstaff', 'GET', ['foo' => 'bar']);
        app()->instance('request', $request);

        $response = $controller->contactstaff($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/contactstaff.php?foo=bar', $response->getTargetUrl());
    }

    public function test_takecontact_rejects_get_request_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/takecontact', 'GET');
        app()->instance('request', $request);

        $response = $controller->takecontact($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Method not allowed', (string) $response->getContent());
    }

    public function test_takecontact_rejects_blank_message_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/takecontact', 'POST', [
            'body' => '',
            'subject' => 'Test',
        ]);
        app()->instance('request', $request);

        $response = $controller->takecontact($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Please enter something', (string) $response->getContent());
    }

    public function test_takecontact_rejects_blank_subject_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/takecontact', 'POST', [
            'body' => 'Hello',
            'subject' => '',
        ]);
        app()->instance('request', $request);

        $response = $controller->takecontact($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Please define a subject', (string) $response->getContent());
    }

    public function test_takecontact_redirects_with_returnto_on_success(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/takecontact', 'POST', [
            'body' => 'Hello staff',
            'subject' => 'Help needed',
            'returnto' => '/index.php',
        ]);
        app()->instance('request', $request);

        $response = $controller->takecontact($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/index.php', $response->getTargetUrl());
    }

    public function test_takecontact_redirects_to_takecontact_page_without_returnto(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/takecontact', 'POST', [
            'body' => 'Hello staff',
            'subject' => 'Help needed',
        ]);
        app()->instance('request', $request);

        $response = $controller->takecontact($request);

        // Without returnto, the controller falls through to legacyPage('takecontact')
        // which renders a View for an authed user.
        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('takecontact.index', $response->name());
    }

    /**
     * Set up the legacy environment: load lang_functions from the language
     * file into Globals and bind LegacyRedisCache to null so that
     * legacyAbortResponse() can render without Redis.
     */
    private function setupLegacyEnvironment(): void
    {
        $langFile = base_path('lang/en/lang_functions.php');
        if (file_exists($langFile)) {
            $lang_functions = [];
            require $langFile;
            app(Globals::class)->set('lang_functions', $lang_functions);
        }

        app()->bind(LegacyRedisCache::class, fn () => null);
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
