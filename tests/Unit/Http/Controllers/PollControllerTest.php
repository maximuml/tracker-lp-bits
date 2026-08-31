<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\PollController;
use App\Http\Requests\PollStoreRequest;
use App\Http\Requests\PollUpdateRequest;
use App\Http\Requests\PollVoteRequest;
use App\Models\Poll;
use App\Models\User;
use App\Repositories\IndexRepository;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class PollControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('pollanswers')->truncate();
        DB::table('polls')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_index_returns_paginated_polls(): void
    {
        Poll::query()->create(['question' => 'Poll A', 'option0' => 'Yes', 'option1' => 'No']);
        Poll::query()->create(['question' => 'Poll B', 'option0' => 'A', 'option1' => 'B']);
        Poll::query()->create(['question' => 'Poll C', 'option0' => 'A', 'option1' => 'B']);

        $controller = app(PollController::class);
        $request = Request::create('/api/polls', 'GET');
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertCount(3, $result['data']['data']);
        // latest('id') => newest first
        $this->assertSame('Poll C', $result['data']['data'][0]['question']);
    }

    public function test_show_returns_single_poll(): void
    {
        $poll = Poll::query()->create(['question' => 'Test Poll', 'option0' => 'Yes', 'option1' => 'No']);

        $controller = app(PollController::class);
        app()->instance('request', request());

        $result = $controller->show($poll);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Test Poll', $result['data']['data']['question']);
    }

    public function test_store_creates_poll(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $this->actingAs($user);

        $controller = app(PollController::class);
        $request = PollStoreRequest::create('/api/polls', 'POST', [
            'question' => 'New Poll',
            'option0' => 'Yes',
            'option1' => 'No',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Poll created', $result['msg']);
        $this->assertSame('New Poll', $result['data']['data']['question']);
        $this->assertDatabaseHas('polls', ['question' => 'New Poll']);
    }

    public function test_update_changes_poll(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $this->actingAs($user);

        $poll = Poll::query()->create(['question' => 'Old Question', 'option0' => 'Yes', 'option1' => 'No']);

        $controller = app(PollController::class);
        $request = PollUpdateRequest::create('/api/polls/'.$poll->id, 'PUT', [
            'question' => 'New Question',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $result = $controller->update($request, $poll);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Poll updated', $result['msg']);
        $this->assertSame('New Question', $result['data']['data']['question']);
        $this->assertDatabaseHas('polls', ['id' => $poll->id, 'question' => 'New Question']);
    }

    public function test_destroy_deletes_poll(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $this->actingAs($user);

        $poll = Poll::query()->create(['question' => 'Delete Me', 'option0' => 'Yes', 'option1' => 'No']);

        $controller = app(PollController::class);
        app()->instance('request', request());

        $result = $controller->destroy($poll);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Poll deleted', $result['msg']);
        $this->assertTrue($result['data']['success']);
        $this->assertDatabaseMissing('polls', ['id' => $poll->id]);
    }

    public function test_latest_returns_no_poll_when_none_active(): void
    {
        /** @var IndexRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(IndexRepository::class);
        $repo->shouldReceive('getCurrentPoll')->once()->andReturn(null);
        app()->instance(IndexRepository::class, $repo);

        $controller = app(PollController::class);
        app()->instance('request', request());

        $result = $controller->latest();

        $this->assertSame(0, $result['ret']);
        $this->assertSame('No poll', $result['msg']);
        $this->assertSame([], $result['data']);
    }

    public function test_latest_returns_active_poll(): void
    {
        $poll = Poll::query()->create(['question' => 'Active Poll', 'option0' => 'Yes', 'option1' => 'No']);

        /** @var IndexRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(IndexRepository::class);
        $repo->shouldReceive('getCurrentPoll')->once()->andReturn(['id' => $poll->id]);
        app()->instance(IndexRepository::class, $repo);

        $controller = app(PollController::class);
        app()->instance('request', request());

        $result = $controller->latest();

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Active Poll', $result['data']['data']['question']);
    }

    public function test_vote_records_vote(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $this->actingAs($user);

        $poll = Poll::query()->create(['question' => 'Vote Poll', 'option0' => 'Yes', 'option1' => 'No']);

        app(CurrentUser::class)->set(['id' => $user->id]);

        /** @var IndexRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(IndexRepository::class);
        $repo->shouldReceive('hasVoted')->once()->with($poll->id, $user->id)->andReturn(false);
        $repo->shouldReceive('recordPollVote')->once()->with($poll->id, $user->id, 0)->andReturn(true);
        app()->instance(IndexRepository::class, $repo);

        $controller = app(PollController::class);
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => $poll->id,
            'choice' => 0,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $result = $controller->vote($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Vote recorded', $result['msg']);
        $this->assertTrue($result['data']['success']);
    }

    public function test_vote_fails_when_already_voted(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $this->actingAs($user);

        $poll = Poll::query()->create(['question' => 'Vote Poll', 'option0' => 'Yes', 'option1' => 'No']);

        app(CurrentUser::class)->set(['id' => $user->id]);

        /** @var IndexRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(IndexRepository::class);
        $repo->shouldReceive('hasVoted')->once()->with($poll->id, $user->id)->andReturn(true);
        app()->instance(IndexRepository::class, $repo);

        $controller = app(PollController::class);
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => $poll->id,
            'choice' => 0,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $result = $controller->vote($request);

        $this->assertSame(-1, $result['ret']);
        $this->assertSame('Already voted', $result['msg']);
    }

    public function test_vote_fails_when_poll_not_found(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $this->actingAs($user);

        app(CurrentUser::class)->set(['id' => $user->id]);

        $controller = app(PollController::class);
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => 99999,
            'choice' => 0,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $result = $controller->vote($request);

        $this->assertSame(-1, $result['ret']);
        $this->assertSame('Poll not found', $result['msg']);
    }
}
