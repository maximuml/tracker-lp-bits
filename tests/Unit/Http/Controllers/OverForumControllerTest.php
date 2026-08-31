<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\OverForumController;
use App\Models\OverForum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OverForumControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('overforums')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_index_returns_all_over_forums_ordered_by_sort(): void
    {
        OverForum::query()->create(['name' => 'Beta', 'sort' => 20, 'description' => '']);
        OverForum::query()->create(['name' => 'Alpha', 'sort' => 10, 'description' => '']);
        OverForum::query()->create(['name' => 'Gamma', 'sort' => 30, 'description' => '']);

        $controller = app(OverForumController::class);
        app()->instance('request', request());

        $result = $controller->index();

        $this->assertSame(0, $result['ret']);
        $this->assertCount(3, $result['data']['data']);
        $this->assertSame('Alpha', $result['data']['data'][0]['name']);
        $this->assertSame('Beta', $result['data']['data'][1]['name']);
        $this->assertSame('Gamma', $result['data']['data'][2]['name']);
    }

    public function test_index_returns_empty_list_when_no_over_forums(): void
    {
        $controller = app(OverForumController::class);
        app()->instance('request', request());

        $result = $controller->index();

        $this->assertSame(0, $result['ret']);
        $this->assertSame([], $result['data']['data']);
    }
}
