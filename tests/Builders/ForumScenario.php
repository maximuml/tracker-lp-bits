<?php

declare(strict_types=1);

namespace Tests\Builders;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * W3-05: Realistic forum scenario builder.
 *
 * Creates a dataset that mimics a real forum:
 * - Multiple forums with different access levels
 * - Topics in each forum with varying post counts
 * - Multiple users posting in different topics
 * - Some topics are sticky, some are locked
 *
 * Usage:
 *   $scenario = ForumScenario::create()
 *       ->withForums(3)
 *       ->withTopicsPerForum(5)
 *       ->withPostsPerTopic(10)
 *       ->build();
 */
final class ForumScenario
{
    /** @var list<Forum> */
    public array $forums = [];

    /** @var list<Topic> */
    public array $topics = [];

    /** @var list<Post> */
    public array $posts = [];

    /** @var Collection<int, User> */
    public Collection $users;

    private int $forumCount = 3;

    private int $topicsPerForum = 5;

    private int $postsPerTopic = 10;

    private int $userCount = 5;

    public static function create(): self
    {
        return new self;
    }

    public function withForums(int $count): self
    {
        $this->forumCount = $count;

        return $this;
    }

    public function withTopicsPerForum(int $count): self
    {
        $this->topicsPerForum = $count;

        return $this;
    }

    public function withPostsPerTopic(int $count): self
    {
        $this->postsPerTopic = $count;

        return $this;
    }

    public function withUsers(int $count): self
    {
        $this->userCount = $count;

        return $this;
    }

    public function build(): self
    {
        // Create users
        $this->users = User::factory()->count($this->userCount)->create();
        $userIds = $this->users->pluck('id')->toArray();

        // Create forums
        $this->forums = Forum::factory()->count($this->forumCount)->create()->all();

        // Create topics and posts
        foreach ($this->forums as $forum) {
            for ($t = 0; $t < $this->topicsPerForum; $t++) {
                $authorId = $userIds[array_rand($userIds)];

                $topic = Topic::factory()->create([
                    'forumid' => $forum->id,
                    'userid' => $authorId,
                    'subject' => "Topic-{$forum->id}-{$t}",
                    'locked' => $t % 5 === 0 ? 1 : 0,
                    'sticky' => $t % 7 === 0 ? 1 : 0,
                ]);
                $this->topics[] = $topic;

                for ($p = 0; $p < $this->postsPerTopic; $p++) {
                    $posterId = $userIds[array_rand($userIds)];

                    $post = Post::factory()->create([
                        'topicid' => $topic->id,
                        'userid' => $posterId,
                        'body' => "Post content {$p} in topic {$topic->id}",
                    ]);
                    $this->posts[] = $post;
                }
            }
        }

        return $this;
    }

    public function topicCount(): int
    {
        return count($this->topics);
    }

    public function postCount(): int
    {
        return count($this->posts);
    }
}
