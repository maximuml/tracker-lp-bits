<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Enums\UserClass;
use App\Http\Controllers\SettingsController;
use App\Models\User;
use App\Repositories\TagRepository;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

final class SettingsControllerTest extends TestCase
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

    public function test_settings_redirects_guest_to_settings_php(): void
    {
        $this->bindTagRepository();
        $this->mockCurrentUser(null);

        $controller = app(SettingsController::class);
        $request = Request::create('/settings', 'GET');
        app()->instance('request', $request);

        $response = $controller->settings($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/settings.php', $response->getTargetUrl());
    }

    public function test_settings_denies_access_for_non_sysop_user(): void
    {
        $this->bindTagRepository();
        $this->mockCurrentUser([]);

        $controller = app(SettingsController::class);
        $request = Request::create('/settings', 'GET');
        app()->instance('request', $request);

        $response = $controller->settings($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_settings_post_with_unknown_action_redirects_to_settings_php(): void
    {
        $this->bindTagRepository();

        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::SYSOP->value]);
        $this->actingAs($user);

        $controller = app(SettingsController::class);
        $request = Request::create('/settings', 'POST', ['action' => 'unknown']);
        app()->instance('request', $request);

        $response = $controller->settings($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/settings.php', $response->getTargetUrl());
    }

    /**
     * Bind a mock TagRepository so the controller can be resolved from the container.
     */
    private function bindTagRepository(): void
    {
        /** @var TagRepository&Mockery\MockInterface $tagRepository */
        $tagRepository = Mockery::mock(TagRepository::class);
        app()->instance(TagRepository::class, $tagRepository);
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
