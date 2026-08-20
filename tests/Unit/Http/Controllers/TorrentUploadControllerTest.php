<?php

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Http\Controllers\TorrentUploadController;
use App\Models\Torrent;
use App\Repositories\UploadRepository;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class TorrentUploadControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_legacy_store_redirects_to_uploaded_details_page(): void
    {
        $torrent = new Torrent;
        $torrent->id = 42;

        /** @var UploadRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UploadRepository::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $repository->shouldReceive('upload');
        $expectation->once()->with(Mockery::type(Request::class))->andReturn($torrent);

        $controller = new TorrentUploadController;
        $request = Request::create('/takeupload', 'POST', [
            'name' => 'Test torrent',
            'descr' => 'Description',
            'type' => 1,
        ]);

        $response = $controller->legacyStore($request, $repository);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('details.php?id=42&uploaded=1', $response->getTargetUrl());
    }

    public function test_legacy_store_redirects_to_existing_torrent(): void
    {
        /** @var UploadRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UploadRepository::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $repository->shouldReceive('upload');
        $expectation->once()->andThrow(new TorrentAlreadyExistsException(99, 'Torrent already exists'));

        $controller = new TorrentUploadController;
        $request = Request::create('/takeupload', 'POST', [
            'name' => 'Duplicate',
            'descr' => 'Description',
            'type' => 1,
        ]);

        $response = $controller->legacyStore($request, $repository);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('details.php?id=99&existed=1', $response->getTargetUrl());
    }
}
