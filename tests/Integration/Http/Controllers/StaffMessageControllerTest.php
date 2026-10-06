<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Enums\UserClass;
use App\Http\Controllers\StaffMessageController;
use App\Http\Requests\SendContactStaffRequest;
use App\Http\Requests\SendStaffMessageRequest;
use App\Jobs\BulkUserMessageJob;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
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

    public function test_take_staffmess_redirects_post_to_rest_endpoint(): void
    {
        $controller = app(StaffMessageController::class);
        $request = Request::create('/takestaffmess', 'POST');
        app()->instance('request', $request);

        $response = $controller->takeStaffmess($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(308, $response->getStatusCode());
        $this->assertStringContainsString('/web/staffmess/send', $response->getTargetUrl());
    }

    public function test_send_staff_message_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = SendStaffMessageRequest::create('/web/staffmess/send', 'POST');
        app()->instance('request', $request);

        $response = $controller->sendStaffMessage($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_contactstaff_redirects_guest_to_contactstaff(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/contactstaff', 'GET');
        app()->instance('request', $request);

        $response = $controller->contactstaff($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/contactstaff', $response->getTargetUrl());
    }

    public function test_contactstaff_redirects_guest_preserving_query_string(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = Request::create('/contactstaff', 'GET', ['foo' => 'bar']);
        app()->instance('request', $request);

        $response = $controller->contactstaff($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/contactstaff?foo=bar', $response->getTargetUrl());
    }

    public function test_takecontact_redirects_post_to_rest_endpoint(): void
    {
        $controller = app(StaffMessageController::class);
        $request = Request::create('/takecontact', 'POST');
        app()->instance('request', $request);

        $response = $controller->takecontact($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(308, $response->getStatusCode());
        $this->assertStringContainsString('/web/contactstaff/send', $response->getTargetUrl());
    }

    public function test_send_contact_staff_rejects_blank_message_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = SendContactStaffRequest::create('/web/contactstaff/send', 'POST', [
            'body' => '',
            'subject' => 'Test',
        ]);
        app()->instance('request', $request);

        $response = $controller->sendContactStaff($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Please enter something', (string) $response->getContent());
    }

    public function test_send_contact_staff_rejects_blank_subject_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(StaffMessageController::class);
        $request = SendContactStaffRequest::create('/web/contactstaff/send', 'POST', [
            'body' => 'Hello',
            'subject' => '',
        ]);
        app()->instance('request', $request);

        $response = $controller->sendContactStaff($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('define subject', (string) $response->getContent());
    }

    public function test_send_contact_staff_redirects_with_returnto_on_success(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = SendContactStaffRequest::create('/web/contactstaff/send', 'POST', [
            'body' => 'Hello staff',
            'subject' => 'Help needed',
            'returnto' => '/index.php',
        ]);
        app()->instance('request', $request);

        $response = $controller->sendContactStaff($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/index.php', $response->getTargetUrl());
    }

    public function test_send_contact_staff_renders_success_page_without_returnto(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = SendContactStaffRequest::create('/web/contactstaff/send', 'POST', [
            'body' => 'Hello staff',
            'subject' => 'Help needed',
        ]);
        app()->instance('request', $request);

        $response = $controller->sendContactStaff($request);

        // Without returnto, the controller falls through to legacyPage('takecontact')
        // which renders a View for an authed user.
        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('takecontact.index', $response->name());
    }

    public function test_send_staff_message_denies_non_admin(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::MODERATOR->value]);
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = SendStaffMessageRequest::create('/web/staffmess/send', 'POST', ['msg' => 'hi', 'classes' => [1]]);
        app()->instance('request', $request);

        $response = $controller->sendStaffMessage($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_send_staff_message_rejects_blank_message(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = SendStaffMessageRequest::create('/web/staffmess/send', 'POST', ['msg' => '', 'classes' => [1]]);
        app()->instance('request', $request);

        $response = $controller->sendStaffMessage($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('blank', (string) $response->getContent());
    }

    public function test_send_staff_message_rejects_empty_classes(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = SendStaffMessageRequest::create('/web/staffmess/send', 'POST', ['msg' => 'hello', 'classes' => []]);
        app()->instance('request', $request);

        $response = $controller->sendStaffMessage($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('No valid filter', (string) $response->getContent());
    }

    public function test_send_staff_message_rejects_invalid_class(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = SendStaffMessageRequest::create('/web/staffmess/send', 'POST', ['msg' => 'hello', 'classes' => [-5]]);
        app()->instance('request', $request);

        $response = $controller->sendStaffMessage($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid Class', (string) $response->getContent());
    }

    public function test_send_staff_message_dispatches_bulk_message_job(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $controller = app(StaffMessageController::class);
        $request = SendStaffMessageRequest::create('/web/staffmess/send', 'POST', [
            'msg' => 'Scheduled downtime tonight',
            'subject' => 'Maintenance',
            'classes' => [UserClass::USER->value],
            'sender' => 'system',
        ]);
        app()->instance('request', $request);

        $response = $controller->sendStaffMessage($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/web/staffmess?sent=1', $response->getTargetUrl());
        Queue::assertPushed(BulkUserMessageJob::class, 1);
    }

    /**
     * Set up the legacy environment: bind LegacyRedisCache to null so that
     * legacyAbortResponse() can render without Redis.
     */
    private function setupLegacyEnvironment(): void
    {

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
        $real->set($user);
        app()->instance(CurrentUser::class, $real);
    }
}
