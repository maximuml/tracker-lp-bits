<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\BookmarkController;
use App\Http\Requests\BookmarkRequest;
use App\Models\Bookmark;
use App\Models\User;
use App\Repositories\BookmarkRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class BookmarkControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_adds_bookmark_and_returns_success(): void
    {
        $user = new User;
        $user->id = 1;

        $bookmark = new Bookmark;
        $bookmark->id = 10;
        $bookmark->torrentid = 42;
        $bookmark->userid = 1;

        /** @var BookmarkRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookmarkRepository::class);
        $repository->shouldReceive('add')
            ->once()
            ->with(Mockery::type(User::class), 42)
            ->andReturn($bookmark);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new BookmarkController($repository);
        $request = BookmarkRequest::create('/api/v1/bookmarks', 'POST', ['torrent_id' => 42]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame(10, $result['data']['data']['id']);
        $this->assertSame(42, $result['data']['data']['torrent_id']);
        $this->assertSame(1, $result['data']['data']['user_id']);
    }

    public function test_store_validates_torrent_id_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var BookmarkRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookmarkRepository::class);
        $repository->shouldNotReceive('add');

        $controller = new BookmarkController($repository);
        $request = BookmarkRequest::create('/api/v1/bookmarks', 'POST', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }

    public function test_destroy_removes_bookmark_and_returns_success(): void
    {
        $user = new User;
        $user->id = 1;

        /** @var BookmarkRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookmarkRepository::class);
        $repository->shouldReceive('remove')
            ->once()
            ->with(Mockery::type(User::class), 42)
            ->andReturn(true);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new BookmarkController($repository);
        $request = BookmarkRequest::create('/api/v1/bookmarks/42', 'DELETE', ['torrent_id' => 42]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->destroy($request);

        $this->assertSame(0, $result['ret']);
        $this->assertTrue($result['data']);
    }
}
