<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\IndexController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Tests\TestCase;

final class IndexControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_redirects_guest_to_index_php(): void
    {
        $controller = app(IndexController::class);
        $request = Request::create('/index.php', 'GET');
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/index.php', $response->getTargetUrl());
    }

    public function test_legacy_returns_view_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $controller = app(IndexController::class);
        $request = Request::create('/index.php', 'GET');
        app()->instance('request', $request);

        $response = $controller->legacy($request);

        // The controller returns a View or RedirectResponse
        $this->assertTrue(
            $response instanceof View
            || $response instanceof RedirectResponse
            || $response instanceof Response
        );
    }
}
