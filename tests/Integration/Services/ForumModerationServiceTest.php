<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTOs\Auth\ActorContext;
use App\Models\Topic;
use App\Services\ForumModerationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

/**
 * Pins ForumModerationService authorization gates — the moderation paths
 * must abort for guests/missing topics instead of acting.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumModerationServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;

    private ForumModerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ForumModerationService::class);
    }

    public function test_set_locked_aborts_for_guest(): void
    {
        /** @var Topic $topic */
        $topic = Topic::factory()->create();
        $this->app->instance(ActorContext::class, $this->actor([]));

        $request = Request::create('/forums/moderation', 'POST', [
            'topicid' => $topic->id,
            'locked' => 1,
        ]);

        $this->expectException(HttpResponseException::class);
        $this->service->setLocked($request);
    }

    public function test_set_locked_aborts_for_missing_topic(): void
    {
        $this->app->instance(ActorContext::class, $this->actor(['id' => 1]));

        $request = Request::create('/forums/moderation', 'POST', [
            'topicid' => 99999999,
            'locked' => 1,
        ]);

        $this->expectException(HttpResponseException::class);
        $this->service->setLocked($request);
    }

    public function test_set_sticky_aborts_for_guest(): void
    {
        /** @var Topic $topic */
        $topic = Topic::factory()->create();
        $this->app->instance(ActorContext::class, $this->actor([]));

        $request = Request::create('/forums/moderation', 'POST', [
            'topicid' => $topic->id,
            'sticky' => 'yes',
        ]);

        $this->expectException(HttpResponseException::class);
        $this->service->setSticky($request);
    }

    public function test_delete_topic_aborts_for_guest(): void
    {
        /** @var Topic $topic */
        $topic = Topic::factory()->create();
        $this->app->instance(ActorContext::class, $this->actor([]));

        $request = Request::create('/forums/moderation', 'POST', [
            'topicid' => $topic->id,
        ]);

        $this->expectException(HttpResponseException::class);
        $this->service->deleteTopic($request);
    }
}
