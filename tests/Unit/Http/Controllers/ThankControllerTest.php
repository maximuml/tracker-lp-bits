<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ThankController;
use App\Http\Requests\ThankRequest;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ThankControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null']);
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('thanks')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_index_returns_paginated_thanks_for_torrent(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        Thank::query()->create([
            'userid' => $user->id,
            'torrentid' => $torrent->id,
            'thankdate' => now()->toDateTimeString(),
        ]);

        $controller = app(ThankController::class);
        $request = Request::create('/api/thanks', 'GET', ['torrent_id' => $torrent->id]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result['data']);
    }

    public function test_index_returns_empty_for_torrent_without_thanks(): void
    {
        $controller = app(ThankController::class);
        $request = Request::create('/api/thanks', 'GET', ['torrent_id' => 99999]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_store_thanks_a_torrent(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'owner' => $owner->id,
            'sp_state' => 1,
        ]);

        Auth::shouldReceive('user')->andReturn($user);

        $controller = app(ThankController::class);
        $request = ThankRequest::create('/api/thanks', 'POST', ['torrent_id' => $torrent->id]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result['data']);
    }

    public function test_show_returns_empty_response(): void
    {
        $controller = app(ThankController::class);

        $response = $controller->show(1);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('', $response->getContent());
    }

    public function test_update_returns_empty_response(): void
    {
        $controller = app(ThankController::class);

        $response = $controller->update(new Request, 1);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_destroy_returns_empty_response(): void
    {
        $controller = app(ThankController::class);

        $response = $controller->destroy(1);

        $this->assertInstanceOf(Response::class, $response);
    }
}
