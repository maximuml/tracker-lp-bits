<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTOs\Auth\ActorContext;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\ForumAccessService;
use App\Support\PageState;
use App\Support\State\ForumState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

/**
 * Forum moderator checks drained from `Support\Forum` — pins
 * isModerator() semantics across post / topic / forum scopes.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumAccessServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;

    private ForumAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        ForumState::instance()->reset();
        PageState::instance()->setLangDir('en');
        $this->service = $this->app->make(ForumAccessService::class);
    }

    private function setActor(int $userId, int $class = 1): void
    {
        $actor = $this->actor(['id' => $userId, 'username' => 'u'.$userId, 'class' => $class]);
        $this->app->instance(ActorContext::class, $actor);

        $user = new User;
        $user->id = $userId;
        $user->class = $class;
        $user->username = 'u'.$userId;
        auth()->login($user);
    }

    private function makeForum(): int
    {
        return (int) DB::table('forums')->insertGetId([
            'name' => 'F'.uniqid(),
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);
    }

    /** @return array{0: Topic, 1: Post} */
    private function makeTopicAndPost(int $forumId, int $topicOwnerId, int $postOwnerId): array
    {
        $topic = Topic::factory()->create([
            'forumid' => $forumId,
            'userid' => $topicOwnerId,
        ]);
        $post = Post::factory()->create([
            'topicid' => $topic->id,
            'userid' => $postOwnerId,
        ]);

        return [$topic, $post];
    }

    public function test_is_moderator_forum_scope(): void
    {
        $forumId = $this->makeForum();
        $mod = User::factory()->create();
        DB::table('forummods')->insert(['forumid' => $forumId, 'userid' => $mod->id]);
        $this->setActor($mod->id);

        $this->assertTrue($this->service->isModerator($forumId, 'forum'));

        $other = User::factory()->create();
        $this->setActor($other->id);
        $this->assertFalse($this->service->isModerator($forumId, 'forum'));
    }

    public function test_is_moderator_topic_and_post_scope(): void
    {
        $forumId = $this->makeForum();
        $mod = User::factory()->create();
        DB::table('forummods')->insert(['forumid' => $forumId, 'userid' => $mod->id]);
        $author = User::factory()->create();
        [$topic, $post] = $this->makeTopicAndPost($forumId, $author->id, $author->id);
        $this->setActor($mod->id);

        $this->assertTrue($this->service->isModerator($topic->id, 'topic'));
        $this->assertTrue($this->service->isModerator($post->id, 'post'));
    }

    public function test_is_moderator_returns_false_for_unknown_scope_and_missing_post(): void
    {
        $this->setActor(User::factory()->create()->id);

        $this->assertFalse($this->service->isModerator(1, 'weird'));
        $this->assertFalse($this->service->isModerator(PHP_INT_MAX, 'post'));
    }
}
