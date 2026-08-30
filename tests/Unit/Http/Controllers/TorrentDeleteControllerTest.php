<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentDeleteController;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Mockery;
use Tests\TestCase;

final class TorrentDeleteControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fast_delete_redirects_when_not_authenticated(): void
    {
        $currentUser = App::make(CurrentUser::class);
        $currentUser->set(null);

        $controller = App::make(TorrentDeleteController::class);
        $request = Request::create('/fastdelete', 'GET', ['id' => 1]);

        $response = $controller->fastDelete($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('fastdelete.php', $response->getTargetUrl());
    }

    public function test_delete_redirects_when_not_authenticated(): void
    {
        $currentUser = App::make(CurrentUser::class);
        $currentUser->set(null);

        $controller = App::make(TorrentDeleteController::class);
        $request = Request::create('/delete', 'GET', ['id' => 1]);

        $response = $controller->delete($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('delete.php', $response->getTargetUrl());
    }
}
