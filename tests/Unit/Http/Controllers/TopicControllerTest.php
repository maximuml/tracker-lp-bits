<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TopicController;
use App\Models\Forum;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class TopicControllerTest extends TestCase
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
        DB::table('topics')->truncate();
        DB::table('forums')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_index_returns_topics_ordered_by_sticky(): void
    {
        $forum = Forum::factory()->create();
        Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Normal', 'sticky' => 0]);
        Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Sticky', 'sticky' => 1]);

        $controller = app(TopicController::class);
        $request = Request::create('/api/topics', 'GET');
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertCount(2, $result['data']['data']);
        $this->assertSame('Sticky', $result['data']['data'][0]['subject']);
    }

    public function test_index_filters_by_forum_id(): void
    {
        $forum1 = Forum::factory()->create();
        $forum2 = Forum::factory()->create();
        Topic::factory()->create(['forumid' => $forum1->id, 'subject' => 'InForum1']);
        Topic::factory()->create(['forumid' => $forum2->id, 'subject' => 'InForum2']);

        $controller = app(TopicController::class);
        $request = Request::create('/api/topics', 'GET', ['forum_id' => $forum1->id]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertCount(1, $result['data']['data']);
        $this->assertSame('InForum1', $result['data']['data'][0]['subject']);
    }

    public function test_show_returns_single_topic(): void
    {
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Test Topic']);

        $controller = app(TopicController::class);
        app()->instance('request', request());

        $result = $controller->show($topic);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Test Topic', $result['data']['data']['subject']);
    }

    public function test_store_creates_topic_with_permission(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasscreate' => 1]);

        $this->actingAs($user);

        /** @var ForumRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldReceive('createTopic')->once()->andReturn(500);
        $repo->shouldReceive('createPost')->once()->andReturn(501);
        $repo->shouldReceive('updateTopicFirstLastPost')->once();
        $repo->shouldReceive('incrementForumTopicCount')->once();
        $repo->shouldReceive('incrementForumPostCount')->once();
        $repo->shouldReceive('updateUserLastPost')->once();
        app()->instance(ForumRepository::class, $repo);

        Topic::factory()->create(['id' => 500, 'forumid' => $forum->id, 'subject' => 'New Topic', 'userid' => $user->id]);

        $controller = app(TopicController::class);
        $request = Request::create('/api/topics', 'POST', [
            'forumid' => $forum->id,
            'subject' => 'New Topic',
            'body' => 'Hello world',
        ]);
        app()->instance('request', $request);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Topic created', $result['msg']);
    }

    public function test_store_denies_when_class_too_low(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $forum = Forum::factory()->create(['minclassread' => 5, 'minclasscreate' => 5]);

        $this->actingAs($user);

        $controller = app(TopicController::class);
        $request = Request::create('/api/topics', 'POST', [
            'forumid' => $forum->id,
            'subject' => 'Test',
            'body' => 'Test',
        ]);
        app()->instance('request', $request);

        $this->expectException(ValidationException::class);

        $controller->store($request);
    }

    public function test_store_aborts_when_not_authenticated(): void
    {
        $forum = Forum::factory()->create();

        $controller = app(TopicController::class);
        $request = Request::create('/api/topics', 'POST', [
            'forumid' => $forum->id,
            'subject' => 'Test',
            'body' => 'Test',
        ]);
        app()->instance('request', $request);

        $this->expectException(HttpException::class);

        $controller->store($request);
    }

    public function test_update_changes_subject_for_owner(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Old', 'userid' => $user->id]);

        $this->actingAs($user);

        /** @var ForumRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldReceive('isModeratorOfTopic')->andReturn(false);
        app()->instance(ForumRepository::class, $repo);

        $controller = app(TopicController::class);
        $request = Request::create('/api/topics/'.$topic->id, 'PUT', [
            'subject' => 'New Subject',
        ]);
        app()->instance('request', $request);

        $result = $controller->update($request, $topic);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('New Subject', $result['data']['data']['subject']);
    }

    public function test_destroy_deletes_topic_with_moderator_permission(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id]);

        $this->actingAs($user);

        /** @var ForumRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldReceive('isModeratorOfTopic')->once()->andReturn(true);
        $repo->shouldReceive('countTopicPosts')->once()->andReturn(5);
        $repo->shouldReceive('deleteTopic')->once();
        app()->instance(ForumRepository::class, $repo);

        $controller = app(TopicController::class);
        app()->instance('request', request());

        $result = $controller->destroy($topic);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Topic deleted', $result['msg']);
        $this->assertTrue($result['data']['success']);
    }
}
