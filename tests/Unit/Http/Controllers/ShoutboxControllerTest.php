<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ShoutboxController;
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
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('shoutbox')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_index_returns_shoutbox_history(): void
    {
        DB::table('shoutbox')->insert([
            ['userid' => 1, 'date' => time(), 'text' => 'Hello', 'type' => 'sb'],
            ['userid' => 1, 'date' => time(), 'text' => 'World', 'type' => 'sb'],
        ]);

        $controller = app(ShoutboxController::class);
        $request = Request::create('/api/shoutbox', 'GET');
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result['data']);
    }

    public function test_index_returns_empty_history(): void
    {
        $controller = app(ShoutboxController::class);
        $request = Request::create('/api/shoutbox', 'GET');
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
    }
}
