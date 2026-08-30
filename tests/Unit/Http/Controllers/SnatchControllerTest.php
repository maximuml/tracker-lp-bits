<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\SnatchController;
use App\Http\Requests\SnatchRequest;
use App\Models\Snatch;
use App\Repositories\TorrentRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class SnatchControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_snatch_list(): void
    {
        $snatch = $this->makeSnatch(1, 10, 5);

        $collection = new Collection([$snatch]);

        /** @var TorrentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentRepository::class);
        $repository->shouldReceive('listSnatches')
            ->once()
            ->with(10)
            ->andReturn($collection);

        $controller = new SnatchController($repository);
        $request = SnatchRequest::create('/api/v1/snatches', 'GET', ['torrent_id' => 10]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_index_returns_empty_list_when_no_snatches(): void
    {
        /** @var TorrentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentRepository::class);
        $repository->shouldReceive('listSnatches')
            ->once()
            ->with(10)
            ->andReturn(new Collection([]));

        $controller = new SnatchController($repository);
        $request = SnatchRequest::create('/api/v1/snatches', 'GET', ['torrent_id' => 10]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_index_validates_torrent_id_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var TorrentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentRepository::class);
        $repository->shouldNotReceive('listSnatches');

        $controller = new SnatchController($repository);
        $request = SnatchRequest::create('/api/v1/snatches', 'GET', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->index($request);
    }

    /**
     * Create a Snatch model instance with all attributes needed by SnatchResource.
     */
    private function makeSnatch(int $id, int $torrentid, int $userid): Snatch
    {
        $snatch = new Snatch;
        $snatch->id = $id;
        $snatch->torrentid = $torrentid;
        $snatch->userid = $userid;
        $snatch->uploaded = 1073741824;
        $snatch->downloaded = 536870912;
        $snatch->to_go = 0;
        $snatch->seedtime = 3600;
        $snatch->leechtime = 1800;
        $snatch->last_action = now()->toDateTimeString();
        $snatch->startdat = now()->toDateTimeString();
        $snatch->completedat = null;
        $snatch->finished = 0;

        return $snatch;
    }
}
