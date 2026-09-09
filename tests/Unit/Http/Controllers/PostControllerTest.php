<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\PostController;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class PostControllerTest extends TestCase
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
        DB::table('posts')->truncate();
        DB::table('topics')->truncate();
        DB::table('forums')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_index_returns_posts_for_topic(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Test Topic']);
        Post::factory()->create(['topicid' => $topic->id, 'body' => 'First post']);
        Post::factory()->create(['topicid' => $topic->id, 'body' => 'Second post']);

        $this->actingAs($user);

        /** @var PostRepository&Mockery\MockInterface $postRepo */
        $postRepo = Mockery::mock(PostRepository::class);
        $postRepo->shouldReceive('getTopicPosts')->once()->andReturn(
            Post::query()->where('topicid', $topic->id)->orderBy('id')->get()
        );
        app()->instance(PostRepository::class, $postRepo);

        $controller = app(PostController::class);
        $request = Request::create('/api/topics/'.$topic->id.'/posts', 'GET');
        app()->instance('request', $request);

        $result = $controller->index($request, $topic);

        $this->assertSame(0, $result['ret']);
        $this->assertCount(2, $result['data']['data']);
    }

    public function test_show_returns_single_post(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Test Topic']);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'Hello world']);

        $this->actingAs($user);

        $controller = app(PostController::class);
        app()->instance('request', request());

        $result = $controller->show($topic, $post);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($post->id, $result['data']['data']['id']);
        $this->assertSame('Hello world', $result['data']['data']['body']);
    }

    public function test_store_creates_reply(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Test Topic', 'locked' => false]);

        $this->actingAs($user);

        /** @var ForumRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldReceive('incrementForumPostCount')->once();
        app()->instance(ForumRepository::class, $repo);

        /** @var PostRepository&Mockery\MockInterface $postRepo */
        $postRepo = Mockery::mock(PostRepository::class);
        $postRepo->shouldReceive('createPost')->once()->andReturn(501);
        $postRepo->shouldReceive('updateUserLastPost')->once();
        app()->instance(PostRepository::class, $postRepo);

        /** @var TopicRepository&Mockery\MockInterface $topicRepo */
        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldReceive('setTopicLastPost')->once();
        app()->instance(TopicRepository::class, $topicRepo);

        Post::factory()->create([
            'id' => 501,
            'topicid' => $topic->id,
            'userid' => $user->id,
            'body' => 'Hello world',
        ]);

        $controller = app(PostController::class);
        $request = Request::create('/api/topics/'.$topic->id.'/posts', 'POST', [
            'body' => 'Hello world',
        ]);
        app()->instance('request', $request);

        $result = $controller->store($request, $topic);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Post created', $result['msg']);
        $this->assertSame(501, $result['data']['data']['id']);
    }

    public function test_store_denies_when_topic_locked(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Locked Topic', 'locked' => true]);

        $this->actingAs($user);

        /** @var TopicRepository&Mockery\MockInterface $topicRepo */
        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldReceive('isModeratorOfTopic')->andReturn(false);
        app()->instance(TopicRepository::class, $topicRepo);

        $controller = app(PostController::class);
        $request = Request::create('/api/topics/'.$topic->id.'/posts', 'POST', [
            'body' => 'Hello world',
        ]);
        app()->instance('request', $request);

        $this->expectException(ValidationException::class);

        $controller->store($request, $topic);
    }

    public function test_store_aborts_when_not_authenticated(): void
    {
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Test Topic']);

        $controller = app(PostController::class);
        $request = Request::create('/api/topics/'.$topic->id.'/posts', 'POST', [
            'body' => 'Hello world',
        ]);
        app()->instance('request', $request);

        $this->expectException(HttpException::class);

        $controller->store($request, $topic);
    }

    public function test_destroy_deletes_post_with_permission(): void
    {
        $user = User::factory()->create(['class' => 10]);
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Test Topic']);
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $user->id, 'body' => 'To delete']);

        $this->actingAs($user);

        /** @var TopicRepository&Mockery\MockInterface $topicRepo */
        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldReceive('isModeratorOfTopic')->once()->andReturn(true);
        app()->instance(TopicRepository::class, $topicRepo);

        /** @var PostRepository&Mockery\MockInterface $postRepo */
        $postRepo = Mockery::mock(PostRepository::class);
        $postRepo->shouldReceive('deletePost')->once();
        app()->instance(PostRepository::class, $postRepo);

        $controller = app(PostController::class);
        app()->instance('request', request());

        $result = $controller->destroy($topic, $post);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Post deleted', $result['msg']);
        $this->assertTrue($result['data']['success']);
    }
}
