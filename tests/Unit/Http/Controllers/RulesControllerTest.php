<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\RulesController;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

final class RulesControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rules_returns_cached_html(): void
    {
        $langFolder = (string) app(Globals::class)->get('CURLANGDIR', 'en');
        Cache::put("{$langFolder}_rules", '<html>Rules Page</html>', 900);

        $controller = app(RulesController::class);
        $request = Request::create('/rules', 'GET');
        app()->instance('request', $request);

        $response = $controller->rules($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Rules Page', $response->getContent());
    }

    public function test_user_agreement_returns_view(): void
    {
        $controller = app(RulesController::class);
        $request = Request::create('/useragreement', 'GET');
        app()->instance('request', $request);

        $response = $controller->userAgreement($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('useragreement.index', $response->name());
    }

    public function test_about_nexus_returns_view(): void
    {
        $controller = app(RulesController::class);
        $request = Request::create('/aboutnexus', 'GET');
        app()->instance('request', $request);

        $response = $controller->aboutNexus($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('aboutnexus.index', $response->name());
    }
}
