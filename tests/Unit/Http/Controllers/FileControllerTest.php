<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\FileController;
use App\Models\File;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FileControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('files')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_index_returns_files_for_torrent(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        File::factory()->create(['torrent' => 100, 'filename' => 'video.mkv', 'size' => 1024]);
        File::factory()->create(['torrent' => 100, 'filename' => 'sub.srt', 'size' => 512]);
        File::factory()->create(['torrent' => 200, 'filename' => 'other.mp4', 'size' => 2048]);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $controller = app(FileController::class);
        $request = Request::create('/api/files', 'GET', ['torrent_id' => 100]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertCount(2, $result['data']['data']);
    }

    public function test_index_returns_empty_for_torrent_without_files(): void
    {
        $controller = app(FileController::class);
        $request = Request::create('/api/files', 'GET', ['torrent_id' => 999]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame([], $result['data']['data']);
    }
}
