<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\InsufficientPermissionException;
use App\Http\Controllers\TorrentController;
use App\Repositories\TorrentRepository;
use App\Repositories\UploadRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class TorrentControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_search_box_returns_search_box_data(): void
    {
        $searchBox = ['id' => 1, 'name' => 'Movies', 'categories' => []];

        /** @var TorrentRepository&Mockery\MockInterface $torrentRepository */
        $torrentRepository = Mockery::mock(TorrentRepository::class);
        $torrentRepository->shouldReceive('getSearchBox')
            ->once()
            ->andReturn($searchBox);

        /** @var UploadRepository&Mockery\MockInterface $uploadRepository */
        $uploadRepository = Mockery::mock(UploadRepository::class);

        $controller = new TorrentController($torrentRepository, $uploadRepository);

        $result = $controller->searchBox();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_query_by_pieces_hash_returns_cached_data(): void
    {
        $cachedData = ['pieces_hash' => 'abc123', 'torrent_id' => 10];

        /** @var TorrentRepository&Mockery\MockInterface $torrentRepository */
        $torrentRepository = Mockery::mock(TorrentRepository::class);
        $torrentRepository->shouldReceive('getPiecesHashCache')
            ->once()
            ->with(['abc123', 'def456'])
            ->andReturn($cachedData);

        /** @var UploadRepository&Mockery\MockInterface $uploadRepository */
        $uploadRepository = Mockery::mock(UploadRepository::class);

        $controller = new TorrentController($torrentRepository, $uploadRepository);
        $request = Request::create('/api/v1/torrents/query-by-pieces-hash', 'POST', [
            'pieces_hash' => ['abc123', 'def456'],
        ]);

        $result = $controller->queryByPiecesHash($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_query_by_pieces_hash_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var TorrentRepository&Mockery\MockInterface $torrentRepository */
        $torrentRepository = Mockery::mock(TorrentRepository::class);
        $torrentRepository->shouldNotReceive('getPiecesHashCache');

        /** @var UploadRepository&Mockery\MockInterface $uploadRepository */
        $uploadRepository = Mockery::mock(UploadRepository::class);

        $controller = new TorrentController($torrentRepository, $uploadRepository);
        $request = Request::create('/api/v1/torrents/query-by-pieces-hash', 'POST', []);

        $controller->queryByPiecesHash($request);
    }

    public function test_query_by_pieces_hash_returns_empty_object_when_no_cache(): void
    {
        /** @var TorrentRepository&Mockery\MockInterface $torrentRepository */
        $torrentRepository = Mockery::mock(TorrentRepository::class);
        $torrentRepository->shouldReceive('getPiecesHashCache')
            ->once()
            ->with(['notfound'])
            ->andReturn([]);

        /** @var UploadRepository&Mockery\MockInterface $uploadRepository */
        $uploadRepository = Mockery::mock(UploadRepository::class);

        $controller = new TorrentController($torrentRepository, $uploadRepository);
        $request = Request::create('/api/v1/torrents/query-by-pieces-hash', 'POST', [
            'pieces_hash' => ['notfound'],
        ]);

        $result = $controller->queryByPiecesHash($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_approval_throws_when_permission_denied(): void
    {
        $this->expectException(InsufficientPermissionException::class);

        /** @var TorrentRepository&Mockery\MockInterface $torrentRepository */
        $torrentRepository = Mockery::mock(TorrentRepository::class);
        $torrentRepository->shouldNotReceive('approval');

        /** @var UploadRepository&Mockery\MockInterface $uploadRepository */
        $uploadRepository = Mockery::mock(UploadRepository::class);

        $controller = new TorrentController($torrentRepository, $uploadRepository);
        $request = Request::create('/api/v1/torrents/approval', 'POST', [
            'torrent_id' => 1,
            'approval_status' => 1,
        ]);

        $controller->approval($request);
    }
}
