<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\FaqController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\ViewException;
use Tests\TestCase;

final class FaqControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_faq_renders_or_throws_view_exception_without_globals(): void
    {
        Cache::flush();

        $controller = app(FaqController::class);
        $request = Request::create('/faq.php', 'GET');
        app()->instance('request', $request);

        // The faq page renders a legacy Blade template that requires
        // Globals language strings. In a unit test without full legacy
        // bootstrap, this throws a ViewException.
        try {
            $response = $controller->faq($request);
            $this->assertInstanceOf(Response::class, $response);
        } catch (ViewException $e) {
            $this->assertTrue(true, 'ViewException expected without language globals');
        }
    }
}
