<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentEditController;
use App\Http\Requests\TorrentEditRequest;
use App\Models\Torrent;
use App\Repositories\TorrentEditRepository;
use Mockery;
use Tests\TestCase;

final class TorrentEditControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_legacy_update_redirects_to_edited_details_page(): void
    {
        $torrent = new Torrent;
        $torrent->id = 42;

        /** @var TorrentEditRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentEditRepository::class);
        $repository->shouldReceive('update')->once()->with(Mockery::type(TorrentEditRequest::class))->andReturn($torrent);

        $controller = app(TorrentEditController::class);
        $request = TorrentEditRequest::create('/takeedit', 'POST', [
            'id' => 42,
            'name' => 'Updated torrent',
            'descr' => 'Updated description',
            'type' => 1,
        ]);

        $response = $controller->legacyUpdate($request, $repository);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('details.php?id=42&edited=1', $response->getTargetUrl());
    }

    public function test_legacy_update_honors_returnto(): void
    {
        $torrent = new Torrent;
        $torrent->id = 42;

        /** @var TorrentEditRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentEditRepository::class);
        $repository->shouldReceive('update')->once()->andReturn($torrent);

        $controller = app(TorrentEditController::class);
        $request = TorrentEditRequest::create('/takeedit', 'POST', [
            'id' => 42,
            'name' => 'Updated torrent',
            'descr' => 'Updated description',
            'type' => 1,
            'returnto' => 'userdetails.php?id=7',
        ]);

        $response = $controller->legacyUpdate($request, $repository);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('http://localhost/userdetails.php?id=7', $response->getTargetUrl());
    }

    public function test_legacy_update_rejects_external_returnto(): void
    {
        $torrent = new Torrent;
        $torrent->id = 42;

        /** @var TorrentEditRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentEditRepository::class);
        $repository->shouldReceive('update')->once()->andReturn($torrent);

        $controller = app(TorrentEditController::class);
        $request = TorrentEditRequest::create('/takeedit', 'POST', [
            'id' => 42,
            'name' => 'Updated torrent',
            'descr' => 'Updated description',
            'type' => 1,
            'returnto' => 'https://evil.example.com/',
        ]);

        $response = $controller->legacyUpdate($request, $repository);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('http://localhost/details.php?id=42&edited=1', $response->getTargetUrl());
    }
}
