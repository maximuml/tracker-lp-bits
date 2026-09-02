<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Enums\UserClass;
use App\Http\Controllers\WebCommentController;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\CommentRepository;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class WebCommentControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::resetState();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_returns_view_for_creating_a_comment(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['owner' => $user->id]);
        $this->actingAs($user, 'nexus-web');

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('getParent')->once()->with($torrent->id, 'torrent')->andReturn([
            'name' => 'Test Torrent',
            'owner' => $user->id,
        ]);
        app()->instance(CommentRepository::class, $repository);

        $controller = app(WebCommentController::class);
        $request = Request::create('/comment.php', 'GET', [
            'type' => 'torrent',
            'pid' => $torrent->id,
        ]);
        app()->instance('request', $request);

        $response = $controller->create($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('comments.create', $response->name());
    }

    public function test_store_creates_comment_and_redirects(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['owner' => $user->id]);
        $this->actingAs($user, 'nexus-web');

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('getParent')->once()->andReturn([
            'name' => 'Test Torrent',
            'owner' => $user->id,
        ]);
        $repository->shouldReceive('create')->once()->andReturn(42);
        app()->instance(CommentRepository::class, $repository);

        $controller = app(WebCommentController::class);
        $request = StoreCommentRequest::create('/comment.php', 'POST', [
            'type' => 'torrent',
            'pid' => $torrent->id,
            'body' => 'Hello world',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $response = $controller->store($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('details.php?id='.$torrent->id, $response->getTargetUrl());
        $this->assertStringContainsString('#42', $response->getTargetUrl());
    }

    public function test_edit_returns_view_for_editing(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user, 'nexus-web');

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('getForEdit')->once()->with(10, 'torrent')->andReturn([
            'user' => $user->id,
            'parent_id' => 5,
            'name' => 'Test Torrent',
            'text' => 'original text',
        ]);
        app()->instance(CommentRepository::class, $repository);

        $controller = app(WebCommentController::class);
        $request = Request::create('/comment.php', 'GET', ['type' => 'torrent']);
        app()->instance('request', $request);

        $response = $controller->edit($request, 10);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('comments.edit', $response->name());
    }

    public function test_update_updates_comment_and_redirects(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user, 'nexus-web');

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('getForEdit')->once()->with(10, 'torrent')->andReturn([
            'user' => $user->id,
            'parent_id' => 5,
            'name' => 'Test Torrent',
            'text' => 'old text',
        ]);
        $repository->shouldReceive('update')->once()->with(10, 'new text', $user->id);
        app()->instance(CommentRepository::class, $repository);

        $controller = app(WebCommentController::class);
        $request = UpdateCommentRequest::create('/comment.php', 'POST', [
            'type' => 'torrent',
            'body' => 'new text',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $response = $controller->update($request, 10);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('details.php?id=5', $response->getTargetUrl());
    }

    public function test_destroy_returns_confirmation_view_when_not_sure(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user, 'nexus-web');

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        app()->instance(CommentRepository::class, $repository);

        $controller = app(WebCommentController::class);
        $request = Request::create('/comment.php', 'GET', ['type' => 'torrent']);
        app()->instance('request', $request);

        $response = $controller->destroy($request, 10);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('comments.delete', $response->name());
    }

    public function test_destroy_deletes_comment_and_redirects(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user, 'nexus-web');

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('getForDelete')->once()->with(10, 'torrent')->andReturn([
            'pid' => 5,
            'user' => $user->id,
        ]);
        $repository->shouldReceive('delete')->once()->with(10, 'torrent', 5)->andReturn(true);
        app()->instance(CommentRepository::class, $repository);

        $controller = app(WebCommentController::class);
        $request = Request::create('/comment.php', 'GET', ['type' => 'torrent', 'sure' => '1']);
        app()->instance('request', $request);

        $response = $controller->destroy($request, 10);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('details.php?id=5', $response->getTargetUrl());
    }

    public function test_original_returns_view_with_original_comment_text(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $this->actingAs($user, 'nexus-web');

        /** @var CommentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(CommentRepository::class);
        $repository->shouldReceive('getForViewOriginal')->once()->with(10, 'torrent')->andReturn([
            'ori_text' => 'original text',
            'torrent' => 5,
            'name' => 'Test Torrent',
        ]);
        app()->instance(CommentRepository::class, $repository);

        $controller = app(WebCommentController::class);
        $request = Request::create('/comment.php', 'GET', ['type' => 'torrent']);
        app()->instance('request', $request);

        $response = $controller->original($request, 10);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('comments.original', $response->name());
    }
}
