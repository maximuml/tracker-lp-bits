<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ShoutboxController;
use App\Repositories\ShoutboxRepository;
use App\Services\ShoutboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ShoutboxControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null']);
    }

    public function test_index_returns_shoutbox_history(): void
    {
        DB::table('shoutbox')->insert([
            ['userid' => 10, 'date' => 1700000000, 'text' => 'hello', 'type' => 'sb'],
            ['userid' => 20, 'date' => 1700000001, 'text' => 'world', 'type' => 'sb'],
        ]);

        $controller = new ShoutboxController(new ShoutboxRepository, app(ShoutboxService::class));
        $request = Request::create('/api/v1/shoutbox', 'GET', ['page' => 1]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(2, $result['data']['total']);
        $this->assertSame(1, $result['data']['page']);
        $this->assertCount(2, $result['data']['data']);
        // Ordered by date DESC, so the second insert comes first.
        $this->assertSame('world', $result['data']['data'][0]['text']);
        $this->assertSame('hello', $result['data']['data'][1]['text']);
    }
}
