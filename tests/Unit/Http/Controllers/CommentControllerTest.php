<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\CommentController;
use App\Models\Comment;
use App\Models\User;
use App\Repositories\CommentRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

final class CommentControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_paginated_comment_list(): void
    {
        $user = new User;
        $user->id = 1;

        $paginator = new LengthAwarePaginator([], 0, 20, 1);

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('getList')->once()->andReturn($paginator);

        Auth::shouldReceive('user')->andReturn($user);

        $controller = new CommentController($repository);
        $request = Request::create('/api/comments', 'GET', ['type' => 'torrent', 'parent_id' => 1]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('comment.list', $result['msg']);
        $this->assertIsArray($result['data']);
        $this->assertArrayHasKey('data', $result['data']);
        $this->assertSame([], $result['data']['data']);
    }

    public function test_store_creates_torrent_comment(): void
    {
        $user = new User;
        $user->id = 1;

        $comment = new Comment;
        $comment->id = 42;
        $comment->text = 'hello world';
        $comment->editedby = 0;

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('store')->once()->andReturn($comment);

        Auth::shouldReceive('user')->andReturn($user);

        $controller = new CommentController($repository);
        $request = Request::create('/api/comments', 'POST', [
            'type' => 'torrent',
            'torrent_id' => 10,
            'text' => 'hello world',
        ]);
        app()->instance('request', $request);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('comment.store', $result['msg']);
        $this->assertIsArray($result['data']);
        $this->assertArrayHasKey('data', $result['data']);
        $this->assertSame(42, $result['data']['data']['id']);
        $this->assertSame('hello world', $result['data']['data']['text']);
    }

    public function test_store_creates_offer_comment(): void
    {
        $user = new User;
        $user->id = 2;

        $comment = new Comment;
        $comment->id = 99;
        $comment->text = 'nice offer';
        $comment->editedby = 0;

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('store')->once()->andReturn($comment);

        Auth::shouldReceive('user')->andReturn($user);

        $controller = new CommentController($repository);
        $request = Request::create('/api/comments', 'POST', [
            'type' => 'offer',
            'offer_id' => 5,
            'text' => 'nice offer',
        ]);
        app()->instance('request', $request);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(99, $result['data']['data']['id']);
    }

    public function test_store_throws_when_torrent_id_missing(): void
    {
        $user = new User;
        $user->id = 1;

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldNotReceive('store');

        Auth::shouldReceive('user')->andReturn($user);

        $controller = new CommentController($repository);
        $request = Request::create('/api/comments', 'POST', [
            'type' => 'torrent',
            'text' => 'missing parent',
        ]);
        app()->instance('request', $request);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('require torrent_id');

        $controller->store($request);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $user = new User;
        $user->id = 1;

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldNotReceive('store');

        Auth::shouldReceive('user')->andReturn($user);

        $controller = new CommentController($repository);
        $request = Request::create('/api/comments', 'POST', [
            'type' => 'invalid',
            'torrent_id' => 1,
            'text' => 'hello',
        ]);
        app()->instance('request', $request);

        $this->expectException(ValidationException::class);

        $controller->store($request);
    }
}
