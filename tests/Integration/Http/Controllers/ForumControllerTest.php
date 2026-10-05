<?php

namespace Tests\Integration\Http\Controllers;

use App\Http\Controllers\ForumController;
use App\Models\Forum;
use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class ForumControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null']);
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('forums')->delete();
        DB::table('overforums')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function controller(): ForumController
    {
        // ForumService and ForumPageService are final and cannot be mocked.
        // The index/show/destroy methods don't use them, so resolve the
        // controller from the container with real dependencies.
        return app(ForumController::class);
    }

    public function test_legacy_action_redirects_post_mutations_to_rest_endpoints(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        /** @var CurrentUser&MockInterface $currentUser */
        $currentUser = Mockery::mock(CurrentUser::class);
        $currentUser->shouldReceive('get')->andReturn(['id' => $user->id]);
        app()->instance(CurrentUser::class, $currentUser);

        $controller = app(ForumController::class);

        foreach (['post', 'movetopic', 'deletetopic', 'deletepost', 'setlocked', 'hltopic', 'setsticky'] as $action) {
            $request = Request::create('/forums', 'POST', ['action' => $action]);
            app()->instance('request', $request);

            $response = $controller->legacyAction($request);

            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame(308, $response->getStatusCode());
            $this->assertStringEndsWith('/web/forums/'.$action, $response->getTargetUrl());
        }
    }

    public function test_index_returns_all_forums_ordered_by_sort(): void
    {
        Forum::factory()->create(['name' => 'Beta', 'sort' => 20]);
        Forum::factory()->create(['name' => 'Alpha', 'sort' => 10]);
        Forum::factory()->create(['name' => 'Gamma', 'sort' => 30]);

        $controller = $this->controller();
        $request = request();
        app()->instance('request', $request);

        $result = $controller->index();

        $this->assertSame(0, $result['ret']);
        $this->assertCount(3, $result['data']['data']);
        $this->assertSame('Alpha', $result['data']['data'][0]['name']);
        $this->assertSame('Beta', $result['data']['data'][1]['name']);
        $this->assertSame('Gamma', $result['data']['data'][2]['name']);
    }

    public function test_show_returns_single_forum(): void
    {
        $forum = Forum::factory()->create(['name' => 'Test Forum', 'description' => 'A test forum']);

        $controller = $this->controller();
        $request = request();
        app()->instance('request', $request);

        $result = $controller->show($forum);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($forum->id, $result['data']['data']['id']);
        $this->assertSame('Test Forum', $result['data']['data']['name']);
        $this->assertSame('A test forum', $result['data']['data']['description']);
    }

    public function test_destroy_deletes_forum(): void
    {
        $forum = Forum::factory()->create(['name' => 'Doomed Forum']);
        $forumId = $forum->id;

        $controller = $this->controller();
        $request = request();
        app()->instance('request', $request);

        $result = $controller->destroy($forum);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Forum deleted', $result['msg']);
        $this->assertTrue($result['data']['success']);
        $this->assertNull(Forum::query()->find($forumId));
    }
}
