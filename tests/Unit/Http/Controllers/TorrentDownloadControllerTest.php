<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentDownloadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Mockery;
use Tests\TestCase;

final class TorrentDownloadControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_email_gateway_returns_empty_response(): void
    {
        $controller = App::make(TorrentDownloadController::class);
        $request = Request::create('/email-gateway', 'GET');

        $response = $controller->emailGateway($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }
}
