<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Http\Controllers\AdminToolsController;
use App\Http\Requests\LocationPostRequest;
use App\Http\Requests\UserBanLogRequest;
use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class AdminToolsControllerTest extends TestCase
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

    public function test_user_ban_log_redirects_guest_to_user_ban_log(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/user-ban-log', 'GET');
        app()->instance('request', $request);

        $response = $controller->userBanLog($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/web/user-ban-log', $response->getTargetUrl());
    }

    public function test_user_ban_log_redirects_guest_preserving_query_string(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/user-ban-log', 'GET', ['q' => 'spam']);
        app()->instance('request', $request);

        $response = $controller->userBanLog($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/web/user-ban-log?q=spam', $response->getTargetUrl());
    }

    public function test_user_ban_log_denies_regular_user(): void
    {
        $this->mockCurrentUser(['id' => 1, 'username' => 'u', 'class' => 1]);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/user-ban-log', 'GET');
        app()->instance('request', $request);

        $response = $controller->userBanLog($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_user_ban_log_submit_denies_regular_user(): void
    {
        $this->mockCurrentUser(['id' => 1, 'username' => 'u', 'class' => 1]);

        $controller = app(AdminToolsController::class);
        $request = UserBanLogRequest::create('/admin/user-ban-log', 'POST');
        app()->instance('request', $request);

        $response = $controller->userBanLogSubmit($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_clear_cache_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/clearcache', 'GET');
        app()->instance('request', $request);

        $response = $controller->clearCache($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_testip_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/testip', 'GET');
        app()->instance('request', $request);

        $response = $controller->testip($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_location_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/location', 'GET');
        app()->instance('request', $request);

        $response = $controller->location($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Access denied', (string) $response->getContent());
    }

    public function test_location_submit_creates_location_from_post_body(): void
    {
        $this->loginAsSysop();

        $controller = app(AdminToolsController::class);
        $request = LocationPostRequest::create('/web/system/location', 'POST', [
            'add' => 'true',
            'name' => 'HQ',
            'flagpic' => 'hq.gif',
            'location_main' => 'Main',
            'location_sub' => 'Sub',
            'start_ip' => '10.0.0.1',
            'end_ip' => '10.0.0.10',
            'theory_upspeed' => '100',
            'practical_upspeed' => '90',
            'theory_downspeed' => '200',
            'practical_downspeed' => '180',
        ]);
        app()->instance('request', $request);

        $response = $controller->locationSubmit($request);

        $this->assertNotInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseHas('locations', [
            'name' => 'HQ',
            'location_main' => 'Main',
            'start_ip' => '10.0.0.1',
            'end_ip' => '10.0.0.10',
        ]);
    }

    public function test_location_submit_ignores_query_string_fields(): void
    {
        $this->loginAsSysop();

        $controller = app(AdminToolsController::class);
        $request = LocationPostRequest::create(
            '/web/system/location?add=true&name=QueryName&start_ip=10.0.0.1&end_ip=10.0.0.10',
            'POST',
        );
        app()->instance('request', $request);

        $response = $controller->locationSubmit($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
        $this->assertDatabaseMissing('locations', ['name' => 'QueryName']);
    }

    public function test_location_submit_deletes_location_from_post_body(): void
    {
        $this->loginAsSysop();
        $id = (int) DB::table('locations')->insertGetId([
            'name' => 'Doomed',
            'location_main' => 'Main',
            'location_sub' => 'Sub',
            'start_ip' => '10.1.0.1',
            'end_ip' => '10.1.0.10',
        ]);

        $controller = app(AdminToolsController::class);
        $request = LocationPostRequest::create('/web/system/location', 'POST', [
            'sure' => 'yes',
            'delid' => $id,
        ]);
        app()->instance('request', $request);

        $response = $controller->locationSubmit($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Location successfully removed', (string) $response->getContent());
        $this->assertDatabaseMissing('locations', ['id' => $id]);
    }

    public function test_location_submit_updates_location_from_post_body(): void
    {
        $this->loginAsSysop();
        $id = (int) DB::table('locations')->insertGetId([
            'name' => 'Old',
            'location_main' => 'Main',
            'location_sub' => 'Sub',
            'start_ip' => '10.2.0.1',
            'end_ip' => '10.2.0.10',
        ]);

        $controller = app(AdminToolsController::class);
        $request = LocationPostRequest::create('/web/system/location', 'POST', [
            'edited' => '1',
            'id' => $id,
            'name' => 'NewName',
            'flagpic' => 'new.gif',
            'location_main' => 'NewMain',
            'location_sub' => 'NewSub',
            'start_ip' => '10.3.0.1',
            'end_ip' => '10.3.0.10',
            'theory_upspeed' => '1',
            'practical_upspeed' => '1',
            'theory_downspeed' => '1',
            'practical_downspeed' => '1',
        ]);
        app()->instance('request', $request);

        $response = $controller->locationSubmit($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Location has been edited', (string) $response->getContent());
        $this->assertDatabaseHas('locations', [
            'id' => $id,
            'name' => 'NewName',
            'location_main' => 'NewMain',
            'start_ip' => '10.3.0.1',
        ]);
    }

    private function loginAsSysop(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        app(CurrentUser::class)->set($user->toLegacyArray());
    }

    /**
     * Bind a partial mock of CurrentUser that returns the given user array.
     *
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $real->set($user);
        app()->instance(CurrentUser::class, $real);
    }

    /**
     * Set up minimal language strings so abortResponse's stdhead()
     * can render for guest users (no authenticated user block).
     */
    private function setupMinimalLang(): void {}
}
