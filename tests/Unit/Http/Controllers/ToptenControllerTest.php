<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Enums\UserClass;
use App\Http\Controllers\ToptenController;
use App\Models\User;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ToptenControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_legacy_redirects_guest(): void
    {
        $controller = app(ToptenController::class);
        $request = Request::create('/topten', 'GET');
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/topten.php', $response->getTargetUrl());
    }

    public function test_legacy_denies_without_permission(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->actingAs($user);

        $controller = app(ToptenController::class);
        $request = Request::create('/topten', 'GET');
        app()->instance('request', $request);

        $this->expectException(HttpException::class);

        $controller->legacy($request);
    }

    public function test_legacy_returns_page_with_permission(): void
    {
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user);

        $langFolder = (string) app(Globals::class)->get('CURLANGDIR', 'en');
        Cache::put("topten_1_10__{$langFolder}", '<html>Top Ten Page</html>', 3600);

        $controller = app(ToptenController::class);
        $request = Request::create('/topten', 'GET');
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Top Ten Page', $response->getContent());
    }
}
