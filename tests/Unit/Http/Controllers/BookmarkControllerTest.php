<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\BookmarkController;
use App\Http\Requests\BookmarkRequest;
use App\Models\Bookmark;
use App\Models\User;
use App\Repositories\BookmarkRepository;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

final class BookmarkControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_returns_success(): void
    {
        $bookmark = new Bookmark;
        $bookmark->id = 1;
        $bookmark->torrentid = 10;
        $bookmark->userid = 5;

        /** @var BookmarkRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookmarkRepository::class);
        $repository->shouldReceive('add')
            ->once()
            ->with(Mockery::on(fn ($u) => $u instanceof User && $u->id === 5), 10)
            ->andReturn($bookmark);

        $user = new User;
        $user->id = 5;
        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new BookmarkController($repository);
        $request = BookmarkRequest::create('/api/v1/bookmarks', 'POST', ['torrent_id' => 10]);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var BookmarkRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookmarkRepository::class);
        $repository->shouldNotReceive('add');

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new BookmarkController($repository);
        $request = BookmarkRequest::create('/api/v1/bookmarks', 'POST', ['torrent_id' => 10]);

        $controller->store($request);
    }

    public function test_destroy_returns_success(): void
    {
        /** @var BookmarkRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookmarkRepository::class);
        $repository->shouldReceive('remove')
            ->once()
            ->with(Mockery::on(fn ($u) => $u instanceof User && $u->id === 5), 10)
            ->andReturn(true);

        $user = new User;
        $user->id = 5;
        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new BookmarkController($repository);
        $request = BookmarkRequest::create('/api/v1/bookmarks/10', 'DELETE', ['torrent_id' => 10]);

        $result = $controller->destroy($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_destroy_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var BookmarkRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(BookmarkRepository::class);
        $repository->shouldNotReceive('remove');

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new BookmarkController($repository);
        $request = BookmarkRequest::create('/api/v1/bookmarks/10', 'DELETE', ['torrent_id' => 10]);

        $controller->destroy($request);
    }
}
