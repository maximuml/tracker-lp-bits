<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\OfferController;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class OfferControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('offers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_offers(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        DB::table('offers')->insert([
            'userid' => $user->id,
            'name' => 'Test Offer',
            'descr' => 'A test offer description',
            'added' => now()->toDateTimeString(),
            'allowed' => 'allowed',
            'category' => $category->id,
            'yeah' => 5,
            'against' => 1,
            'comments' => 0,
        ]);

        $controller = app(OfferController::class);
        $request = Request::create('/api/offers', 'GET');
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(1, $result['data']['total']);
        $this->assertCount(1, $result['data']['data']);
        $this->assertSame('Test Offer', $result['data']['data'][0]['name']);
    }

    public function test_index_returns_empty_list(): void
    {
        $controller = app(OfferController::class);
        $request = Request::create('/api/offers', 'GET');
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(0, $result['data']['total']);
        $this->assertSame([], $result['data']['data']);
    }
}
