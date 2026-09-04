<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\CommentController;
use App\Http\Requests\PrepareCommentRequest;
use App\Models\Comment;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\CommentRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class CommentControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_paginated_comments(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $torrent = Torrent::factory()->create();
        Comment::factory()->create(['torrent' => $torrent->id, 'user' => $user->id]);

        $repository = new CommentRepository;
        $controller = new CommentController($repository);
        $request = Request::create('/api/comments', 'GET', [
            'type' => 'torrent',
            'parent_id' => $torrent->id,
        ]);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_creates_comment_for_torrent(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $torrent = Torrent::factory()->create();

        $repository = new CommentRepository;
        $controller = new CommentController($repository);
        $request = PrepareCommentRequest::create('/api/comments', 'POST', [
            'type' => 'torrent',
            'torrent_id' => $torrent->id,
            'text' => 'Great torrent!',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertDatabaseHas('comments', [
            'torrent' => $torrent->id,
            'text' => 'Great torrent!',
            'user' => $user->id,
        ]);
    }

    public function test_store_creates_anonymous_comment(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $torrent = Torrent::factory()->create();

        $repository = new CommentRepository;
        $controller = new CommentController($repository);
        $request = PrepareCommentRequest::create('/api/comments', 'POST', [
            'type' => 'torrent',
            'torrent_id' => $torrent->id,
            'text' => 'Anonymous comment',
            'anonymous' => true,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertDatabaseHas('comments', [
            'torrent' => $torrent->id,
            'text' => 'Anonymous comment',
            'anonymous' => true,
        ]);
    }

    public function test_store_fails_without_required_type(): void
    {
        $this->expectException(ValidationException::class);

        $user = User::factory()->create();
        Auth::login($user);

        $repository = new CommentRepository;
        $controller = new CommentController($repository);
        $request = PrepareCommentRequest::create('/api/comments', 'POST', [
            'text' => 'Missing type',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }

    public function test_store_fails_without_text(): void
    {
        $this->expectException(ValidationException::class);

        $user = User::factory()->create();
        Auth::login($user);
        $torrent = Torrent::factory()->create();

        $repository = new CommentRepository;
        $controller = new CommentController($repository);
        $request = PrepareCommentRequest::create('/api/comments', 'POST', [
            'type' => 'torrent',
            'torrent_id' => $torrent->id,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $request->validateResolved();
        $controller->store($request);
    }

    public function test_store_fails_with_invalid_type(): void
    {
        $this->expectException(ValidationException::class);

        $user = User::factory()->create();
        Auth::login($user);

        $repository = new CommentRepository;
        $controller = new CommentController($repository);
        $request = PrepareCommentRequest::create('/api/comments', 'POST', [
            'type' => 'invalid_type',
            'torrent_id' => 1,
            'text' => 'Test',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $controller->store($request);
        $request->validateResolved();
    }

    public function test_store_fails_when_torrent_id_missing_for_torrent_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = User::factory()->create();
        Auth::login($user);

        $repository = new CommentRepository;
        $controller = new CommentController($repository);
        $request = PrepareCommentRequest::create('/api/comments', 'POST', [
            'type' => 'torrent',
            'text' => 'Missing torrent_id',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }

    public function test_store_increments_torrent_comment_count(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $torrent = Torrent::factory()->create(['comments' => 0]);

        $repository = new CommentRepository;
        $controller = new CommentController($repository);
        $request = PrepareCommentRequest::create('/api/comments', 'POST', [
            'type' => 'torrent',
            'torrent_id' => $torrent->id,
            'text' => 'Increment test',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);

        $this->assertSame(1, (int) $torrent->fresh()->comments);
    }
}
