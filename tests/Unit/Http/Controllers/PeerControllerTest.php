<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\PeerController;
use App\Http\Requests\PeerRequest;
use App\Models\Peer;
use App\Repositories\TorrentRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class PeerControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_seeder_and_leecher_lists(): void
    {
        $seeder = $this->makePeer(1, 10, 1);

        $seederList = new Collection([$seeder]);
        $leecherList = new Collection([]);

        /** @var TorrentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentRepository::class);
        $repository->shouldReceive('listPeers')
            ->once()
            ->with(10)
            ->andReturn([
                'seeder_list' => $seederList,
                'leecher_list' => $leecherList,
            ]);

        $controller = new PeerController($repository);
        $request = PeerRequest::create('/api/v1/peers', 'GET', ['torrent_id' => 10]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('seeder_list', $result['data']);
        $this->assertArrayHasKey('leecher_list', $result['data']);
    }

    public function test_index_returns_empty_lists_when_no_peers(): void
    {
        /** @var TorrentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentRepository::class);
        $repository->shouldReceive('listPeers')
            ->once()
            ->with(10)
            ->andReturn([
                'seeder_list' => new Collection([]),
                'leecher_list' => new Collection([]),
            ]);

        $controller = new PeerController($repository);
        $request = PeerRequest::create('/api/v1/peers', 'GET', ['torrent_id' => 10]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame([], $result['data']['seeder_list']);
        $this->assertSame([], $result['data']['leecher_list']);
    }

    public function test_index_validates_torrent_id_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var TorrentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentRepository::class);
        $repository->shouldNotReceive('listPeers');

        $controller = new PeerController($repository);
        $request = PeerRequest::create('/api/v1/peers', 'GET', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->index($request);
    }

    /**
     * Create a Peer model instance with all attributes needed by PeerResource.
     */
    private function makePeer(int $id, int $torrent, int $seeder): Peer
    {
        $peer = new Peer;
        $peer->id = $id;
        $peer->torrent = $torrent;
        $peer->seeder = $seeder;
        $peer->connectable = 1;
        $peer->uploaded = 1073741824;
        $peer->downloaded = 536870912;
        $peer->to_go = 0;
        $peer->peer_id = 'peer123';
        $peer->agent = 'uTorrent';
        $peer->last_action = now()->toDateTimeString();
        $peer->started = now()->toDateTimeString();

        return $peer;
    }
}
