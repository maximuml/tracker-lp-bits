<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\SystemMaintenanceController;
use App\Services\CleanupService;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SystemMaintenanceControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_mysql_stats_redirects_guest_to_mysql_stats_php(): void
    {
        $this->mockCurrentUser(null);
        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/mysql_stats', 'GET');

        $response = $controller->mysqlStats($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/mysql_stats.php', $response->getTargetUrl());
    }

    public function test_mysql_stats_redirects_guest_preserving_query_string(): void
    {
        $this->mockCurrentUser(null);
        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/mysql_stats', 'GET', ['order' => 'foo']);

        $response = $controller->mysqlStats($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/mysql_stats.php?order=foo', $response->getTargetUrl());
    }

    public function test_mysql_stats_aborts_for_non_sysop_user(): void
    {
        $this->mockCurrentUser(['class' => UC_MODERATOR]);
        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/mysql_stats', 'GET');

        $this->expectException(HttpException::class);

        $controller->mysqlStats($request);
    }

    public function test_cron_returns_response_with_trigger_cron_output(): void
    {
        app()->bind(CleanupService::class, function () {
            return new class
            {
                public function triggerCron(): string
                {
                    return 'cron-ok';
                }

                public function runFull(bool $forceAll = false, bool $printProgress = true): string
                {
                    return 'full-ok';
                }
            };
        });

        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/cron', 'GET');

        $response = $controller->cron($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('cron-ok', (string) $response->getContent());
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function test_docleanup_returns_response_with_run_full_output(): void
    {
        app()->bind(CleanupService::class, function () {
            return new class
            {
                public function triggerCron(): string
                {
                    return 'cron-ok';
                }

                public function runFull(bool $forceAll = false, bool $printProgress = true): string
                {
                    return $forceAll ? 'forced' : 'normal';
                }
            };
        });

        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/docleanup', 'GET');

        $response = $controller->docleanup($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('normal', (string) $response->getContent());
        $this->assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function test_docleanup_passes_forceall_flag(): void
    {
        app()->bind(CleanupService::class, function () {
            return new class
            {
                public function triggerCron(): string
                {
                    return 'cron-ok';
                }

                public function runFull(bool $forceAll = false, bool $printProgress = true): string
                {
                    return $forceAll ? 'forced' : 'normal';
                }
            };
        });

        $controller = app(SystemMaintenanceController::class);
        $request = Request::create('/docleanup', 'GET', ['forceall' => '1']);

        $response = $controller->docleanup($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('forced', (string) $response->getContent());
    }

    private function mockCurrentUser(?array $user): void
    {
        $mock = Mockery::mock(new CurrentUser);
        $mock->shouldReceive('get')->andReturn($user);
        app()->instance(CurrentUser::class, $mock);
    }
}
