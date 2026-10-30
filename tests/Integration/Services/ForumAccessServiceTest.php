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
 * Protected-forum visibility and moderator checks drained from
 * `Support\Forum` — pins isModerator() / canViewPost() semantics.
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

    private function protectForum(int $forumId): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'misc.protected_forum'],
            ['value' => (string) $forumId]
        );
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

    public function test_can_view_post_allows_regular_forum(): void
    {
        $forumId = $this->makeForum();
        $author = User::factory()->create();
        [, $post] = $this->makeTopicAndPost($forumId, $author->id, $author->id);
        $viewer = User::factory()->create();
        $this->setActor($viewer->id, 1);

        $this->assertTrue($this->service->canViewPost($viewer->id, $post->id));
    }

    public function test_can_view_post_denies_protected_forum_to_outsider(): void
    {
        $forumId = $this->makeForum();
        $this->protectForum($forumId);
        $author = User::factory()->create();
        [, $post] = $this->makeTopicAndPost($forumId, $author->id, $author->id);
        $outsider = User::factory()->create();
        $this->setActor($outsider->id, 1);

        $this->assertFalse($this->service->canViewPost($outsider->id, $post->id));
    }

    public function test_can_view_post_allows_post_owner_in_protected_forum(): void
    {
        $forumId = $this->makeForum();
        $this->protectForum($forumId);
        $author = User::factory()->create();
        $other = User::factory()->create();
        [, $post] = $this->makeTopicAndPost($forumId, $other->id, $author->id);
        $this->setActor($author->id, 1);

        $this->assertTrue($this->service->canViewPost($author->id, $post->id));
    }

    public function test_can_view_post_allows_topic_owner_in_protected_forum(): void
    {
        $forumId = $this->makeForum();
        $this->protectForum($forumId);
        $topicOwner = User::factory()->create();
        $postAuthor = User::factory()->create();
        [, $post] = $this->makeTopicAndPost($forumId, $topicOwner->id, $postAuthor->id);
        $this->setActor($topicOwner->id, 1);

        $this->assertTrue($this->service->canViewPost($topicOwner->id, $post->id));
    }

    public function test_can_view_post_allows_forum_mod_in_protected_forum(): void
    {
        $forumId = $this->makeForum();
        $this->protectForum($forumId);
        $author = User::factory()->create();
        [, $post] = $this->makeTopicAndPost($forumId, $author->id, $author->id);
        $mod = User::factory()->create();
        DB::table('forummods')->insert(['forumid' => $forumId, 'userid' => $mod->id]);
        $this->setActor($mod->id, 1);

        $this->assertTrue($this->service->canViewPost($mod->id, $post->id));
    }

    public function test_can_view_post_allows_admin_class_in_protected_forum(): void
    {
        $forumId = $this->makeForum();
        $this->protectForum($forumId);
        $author = User::factory()->create();
        [, $post] = $this->makeTopicAndPost($forumId, $author->id, $author->id);
        $admin = User::factory()->create();
        $this->setActor($admin->id, 14);

        $this->assertTrue($this->service->canViewPost($admin->id, $post->id));
    }
}
