<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Enums\UserClass;
use App\Http\Controllers\SystemMaintenanceController;
use App\Jobs\SendLegacyMail;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class SystemMaintenanceControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app()->bind(LegacyRedisCache::class, fn () => null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_mailtest_redirects_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/mailtest', 'GET');
        app()->instance('request', $request);

        $response = $controller->mailtest($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/web/mailtest', $response->getTargetUrl());
    }

    public function test_mailtest_denies_non_sysop(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::MODERATOR->value)->create();
        $this->actingAs($user);

        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/mailtest', 'GET');
        app()->instance('request', $request);

        $response = $controller->mailtest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_mailtest_sendmail_rejects_invalid_email(): void
    {
        $this->loginAsSysop();

        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/mailtest', 'POST', [
            'action' => 'sendmail',
            'email' => 'not-an-email',
        ]);
        app()->instance('request', $request);

        $response = $controller->mailtest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid email address', (string) $response->getContent());
    }

    public function test_mailtest_sendmail_reports_smtp_disabled(): void
    {
        Queue::fake();
        Settings::saveBatch('smtp', ['smtptype' => 'none']);
        Settings::resetCache();
        $this->loginAsSysop();

        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/mailtest', 'POST', [
            'action' => 'sendmail',
            'email' => 'admin@example.com',
        ]);
        app()->instance('request', $request);

        $response = $controller->mailtest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Unable to send mail', (string) $response->getContent());
        Queue::assertNotPushed(SendLegacyMail::class);
    }

    public function test_mailtest_sendmail_queues_job_when_smtp_enabled(): void
    {
        Queue::fake();
        Settings::saveBatch('smtp', ['smtptype' => 'external']);
        Settings::resetCache();
        $this->loginAsSysop();

        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/mailtest', 'POST', [
            'action' => 'sendmail',
            'email' => 'admin@example.com',
        ]);
        app()->instance('request', $request);

        $response = $controller->mailtest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Success', (string) $response->getContent());
        Queue::assertPushed(SendLegacyMail::class);
    }

    private function loginAsSysop(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        app(CurrentUser::class)->set($user->toLegacyArray());
    }

    /**
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
