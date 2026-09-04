<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\PollController;
use App\Http\Requests\PollStoreRequest;
use App\Http\Requests\PollUpdateRequest;
use App\Http\Requests\PollVoteRequest;
use App\Models\Poll;
use App\Repositories\IndexRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    public function test_index_returns_paginated_polls(): void
    {
        Poll::factory()->create(['question' => 'Test poll 1']);
        Poll::factory()->create(['question' => 'Test poll 2']);

        $controller = new PollController;
        $request = PollStoreRequest::create('/api/polls', 'GET', ['limit' => 10]);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_show_returns_single_poll(): void
    {
        $poll = Poll::factory()->create(['question' => 'Show me']);

        $controller = new PollController;
        $result = $controller->show($poll);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
        // PollResource may wrap data in a 'data' key
        $data = $result['data'];
        if (isset($data['data'])) {
            $data = $data['data'];
        }
        $this->assertSame('Show me', $data['question']);
    }

    public function test_store_creates_new_poll(): void
    {
        $controller = new PollController;
        $request = PollStoreRequest::create('/api/polls', 'POST', [
            'question' => 'New poll?',
            'option0' => 'Yes',
            'option1' => 'No',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertDatabaseHas('polls', ['question' => 'New poll?']);
    }

    public function test_update_modifies_poll(): void
    {
        $poll = Poll::factory()->create(['question' => 'Old question']);

        $controller = new PollController;
        $request = PollUpdateRequest::create('/api/polls/'.$poll->id, 'PUT', [
            'question' => 'Updated question',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->update($request, $poll);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Updated question', $poll->fresh()->question);
    }

    public function test_destroy_deletes_poll(): void
    {
        $poll = Poll::factory()->create(['question' => 'Delete me']);

        $controller = new PollController;
        $result = $controller->destroy($poll);

        $this->assertSame(0, $result['ret']);
        $this->assertDatabaseMissing('polls', ['id' => $poll->id]);
    }

    public function test_vote_records_choice_for_valid_option(): void
    {
        $poll = Poll::factory()->create([
            'question' => 'Vote test',
            'option0' => 'Yes',
            'option1' => 'No',
        ]);

        $currentUser = ['id' => 999, 'username' => 'voter'];
        app(CurrentUser::class)->set($currentUser);

        $indexRepo = Mockery::mock(IndexRepository::class);
        $indexRepo->shouldReceive('hasVoted')->once()->with($poll->id, 999)->andReturn(false);
        $indexRepo->shouldReceive('recordPollVote')->once()->with($poll->id, 999, 0);
        app()->instance(IndexRepository::class, $indexRepo);

        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('delete_value')->twice();
        app()->instance(LegacyRedisCache::class, $cache);

        $controller = new PollController;
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => $poll->id,
            'choice' => 0,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->vote($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_vote_records_blank_vote_255(): void
    {
        $poll = Poll::factory()->create([
            'question' => 'Blank vote test',
            'option0' => 'Yes',
            'option1' => 'No',
        ]);

        $currentUser = ['id' => 998, 'username' => 'blank_voter'];
        app(CurrentUser::class)->set($currentUser);

        $indexRepo = Mockery::mock(IndexRepository::class);
        $indexRepo->shouldReceive('hasVoted')->once()->with($poll->id, 998)->andReturn(false);
        $indexRepo->shouldReceive('recordPollVote')->once()->with($poll->id, 998, 255);
        app()->instance(IndexRepository::class, $indexRepo);

        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('delete_value')->twice();
        app()->instance(LegacyRedisCache::class, $cache);

        $controller = new PollController;
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => $poll->id,
            'choice' => 255,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->vote($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_vote_fails_for_nonexistent_poll(): void
    {
        $currentUser = ['id' => 997, 'username' => 'tester'];
        app(CurrentUser::class)->set($currentUser);

        $controller = new PollController;
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => 999999,
            'choice' => 0,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->vote($request);

        $this->assertNotSame(0, $result['ret']);
    }

    public function test_vote_fails_for_invalid_choice(): void
    {
        $poll = Poll::factory()->create([
            'question' => 'Invalid choice test',
            'option0' => 'Yes',
            'option1' => 'No',
            'option2' => '',
            'option3' => '',
        ]);

        $currentUser = ['id' => 996, 'username' => 'tester'];
        app(CurrentUser::class)->set($currentUser);

        $controller = new PollController;
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => $poll->id,
            'choice' => 5, // option5 is empty
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->vote($request);

        $this->assertNotSame(0, $result['ret']);
    }

    public function test_vote_fails_if_already_voted(): void
    {
        $poll = Poll::factory()->create([
            'question' => 'Already voted test',
            'option0' => 'Yes',
            'option1' => 'No',
        ]);

        $currentUser = ['id' => 995, 'username' => 'tester'];
        app(CurrentUser::class)->set($currentUser);

        $indexRepo = Mockery::mock(IndexRepository::class);
        $indexRepo->shouldReceive('hasVoted')->once()->with($poll->id, 995)->andReturn(true);
        $indexRepo->shouldNotReceive('recordPollVote');
        app()->instance(IndexRepository::class, $indexRepo);

        $controller = new PollController;
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => $poll->id,
            'choice' => 0,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->vote($request);

        $this->assertNotSame(0, $result['ret']);
    }

    public function test_vote_invalidates_legacy_cache(): void
    {
        $poll = Poll::factory()->create([
            'question' => 'Cache invalidation test',
            'option0' => 'Yes',
            'option1' => 'No',
        ]);

        $currentUser = ['id' => 994, 'username' => 'tester'];
        app(CurrentUser::class)->set($currentUser);

        $indexRepo = Mockery::mock(IndexRepository::class);
        $indexRepo->shouldReceive('hasVoted')->once()->andReturn(false);
        $indexRepo->shouldReceive('recordPollVote')->once();
        app()->instance(IndexRepository::class, $indexRepo);

        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('delete_value')
            ->with('current_poll_content')
            ->once();
        $cache->shouldReceive('delete_value')
            ->with('current_poll_result', true)
            ->once();
        app()->instance(LegacyRedisCache::class, $cache);

        $controller = new PollController;
        $request = PollVoteRequest::create('/api/polls/vote', 'POST', [
            'poll_id' => $poll->id,
            'choice' => 0,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->vote($request);

        // Mockery verifies expectations on close()
        $this->assertTrue(true);
    }
}
