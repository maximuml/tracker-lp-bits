<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Forum;
use App\Models\Language;
use App\Models\Message;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase20ApiParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_usercp_settings_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['usercp:settings']);

        $this->getJson('/api/v1/usercp/settings')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_usercp_personal_settings_updates_user(): void
    {
        $user = User::factory()->create();
        $country = (int) DB::table('countries')->value('id');

        Sanctum::actingAs($user, ['usercp:settings']);

        $this->postJson('/api/v1/usercp/settings', [
            'parked' => 'no',
            'acceptpms' => 'friends',
            'commentpm' => 'no',
            'gender' => 'Male',
            'country' => $country,
            'info' => 'Updated info',
            'notifs' => ['topic_reply' => 'yes'],
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.acceptpms', 'friends')
            ->assertJsonPath('data.gender', 'Male');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'acceptpms' => 'friends',
            'gender' => 'Male',
        ]);
    }

    public function test_usercp_forum_settings_updates_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['usercp:settings']);

        $this->postJson('/api/v1/usercp/forum', [
            'topicsperpage' => 25,
            'postsperpage' => 15,
            'avatars' => 'yes',
            'signatures' => 'yes',
            'clicktopic' => 'firstpage',
            'signature' => 'My signature',
            'ttlastpost' => 'yes',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.topicsperpage', 25)
            ->assertJsonPath('data.postsperpage', 15)
            ->assertJsonPath('data.showlastpost', 'yes');
    }

    public function test_usercp_tracker_settings_updates_user(): void
    {
        $user = User::factory()->create();
        $style = (int) DB::table('stylesheets')->value('id') ?: null;
        $language = Language::query()->value('id');

        Sanctum::actingAs($user, ['usercp:settings']);

        $this->postJson('/api/v1/usercp/tracker', [
            'torrentsperpage' => 30,
            'pmnum' => 5,
            'sbnum' => 20,
            'sbrefresh' => 60,
            'timetype' => 'timeadded',
            'appendsticky' => 'yes',
            'appendnew' => 'yes',
            'appendpromotion' => 'yes',
            'appendpicked' => 'yes',
            'dlicon' => 'yes',
            'bmicon' => 'yes',
            'showcomnum' => 'yes',
            'showdescription' => 'yes',
            'smalldescr' => 'yes',
            'showcomment' => 'yes',
            'fontsize' => 'large',
            'pmnotif' => 'yes',
            'emailnotif' => 'yes',
            'incldead' => 0,
            'spstate' => '1',
            'inclbookmarked' => 'yes',
            'cat' . (Category::query()->value('id') ?? 1) => 'yes',
            'stylesheet' => $style,
            'sitelanguage' => $language,
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.torrentsperpage', 30)
            ->assertJsonPath('data.pmnum', 5)
            ->assertJsonPath('data.sbnum', 20)
            ->assertJsonPath('data.sbrefresh', 60)
            ->assertJsonPath('data.timetype', 'timeadded')
            ->assertJsonPath('data.fontsize', 'large');
    }

    public function test_usercp_security_changes_password_and_resets_passkey(): void
    {
        $user = User::factory()->create();
        $oldPasskey = $user->passkey;

        Sanctum::actingAs($user, ['usercp:settings']);

        $this->postJson('/api/v1/usercp/security', [
            'current_password' => '123456',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
            'privacy' => 'strong',
            'resetpasskey' => '1',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.id', $user->id);

        $user->refresh();

        $this->assertNotEquals($oldPasskey, $user->passkey);
        $this->assertTrue(
            app(\App\Services\WebAuthService::class)->validatePassword($user, 'newpass123')
        );
    }

    public function test_messages_lifecycle(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        Sanctum::actingAs($sender, ['message:store']);

        $sendResponse = $this->postJson('/api/v1/messages', [
            'receiver' => $receiver->id,
            'subject' => 'Hello',
            'msg' => 'World',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.subject', 'Hello');

        $messageId = $sendResponse->json('data.data.id');

        Sanctum::actingAs($receiver, ['message:list', 'message:unread', 'message:show', 'message:update', 'message:destroy']);

        $this->getJson('/api/v1/messages?mailbox=1')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.0.id', $messageId);

        $this->getJson('/api/v1/messages-unread')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.0.id', $messageId);

        $this->getJson('/api/v1/messages/' . $messageId)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.id', $messageId)
            ->assertJsonPath('data.data.msg', 'World');

        $this->patchJson('/api/v1/messages/' . $messageId, [
            'unread' => 'no',
            'location' => 2,
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0);

        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'unread' => 'no',
            'location' => 2,
        ]);

        $this->deleteJson('/api/v1/messages/' . $messageId)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseMissing('messages', ['id' => $messageId]);
    }

    public function test_forums_crud(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin, ['forum:manage', 'forum:list']);

        $createResponse = $this->postJson('/api/v1/forums', [
            'name' => 'Test Forum',
            'description' => 'A test forum',
            'forid' => 0,
            'minclassread' => 1,
            'minclasswrite' => 1,
            'minclasscreate' => 1,
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.name', 'Test Forum');

        $forumId = $createResponse->json('data.data.id');

        $this->getJson('/api/v1/forums/' . $forumId)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.id', $forumId);

        $this->getJson('/api/v1/forums')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.0.id', static fn ($id) => $id > 0);

        $this->patchJson('/api/v1/forums/' . $forumId, [
            'name' => 'Updated Forum',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.name', 'Updated Forum');

        $this->deleteJson('/api/v1/forums/' . $forumId)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.success', true);
    }

    public function test_topics_crud(): void
    {
        $admin = User::factory()->admin()->create();
        $forum = Forum::factory()->create();

        Sanctum::actingAs($admin, ['topic:manage', 'topic:list']);

        $createResponse = $this->postJson('/api/v1/topics', [
            'forumid' => $forum->id,
            'subject' => 'Test Topic',
            'body' => 'First post body',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.subject', 'Test Topic');

        $topicId = $createResponse->json('data.data.id');

        $topic = Topic::findOrFail($topicId);

        $this->getJson('/api/v1/topics?forum_id=' . $forum->id)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.0.id', $topicId);

        $this->getJson('/api/v1/topics/' . $topicId)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.id', $topicId);

        $this->patchJson('/api/v1/topics/' . $topicId, [
            'subject' => 'Updated Topic',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.subject', 'Updated Topic');

        $this->deleteJson('/api/v1/topics/' . $topicId)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
    }

    public function test_posts_crud(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $forum = Forum::factory()->create();

        Sanctum::actingAs($admin, ['topic:manage', 'topic:list']);

        $topicResponse = $this->postJson('/api/v1/topics', [
            'forumid' => $forum->id,
            'subject' => 'Topic for posts',
            'body' => 'First post',
        ])
            ->assertStatus(200);

        $topicId = $topicResponse->json('data.data.id');
        $topic = Topic::findOrFail($topicId);

        Sanctum::actingAs($user, ['topic:list']);

        $postResponse = $this->postJson('/api/v1/topics/' . $topicId . '/posts', [
            'body' => 'Reply body',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.body', 'Reply body');

        $postId = $postResponse->json('data.data.id');

        $this->getJson('/api/v1/topics/' . $topicId . '/posts')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.1.id', $postId);

        $this->getJson('/api/v1/topics/' . $topicId . '/posts/' . $postId)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.id', $postId);

        $this->patchJson('/api/v1/topics/' . $topicId . '/posts/' . $postId, [
            'body' => 'Updated reply body',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.body', 'Updated reply body');

        $this->deleteJson('/api/v1/topics/' . $topicId . '/posts/' . $postId)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseMissing('posts', ['id' => $postId]);

        Sanctum::actingAs($admin, ['topic:manage', 'topic:list']);
        $this->deleteJson('/api/v1/topics/' . $topicId)
            ->assertStatus(200);
    }

    public function test_offers_index_returns_empty_list(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['offer:list']);

        $this->getJson('/api/v1/offers')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_shoutbox_index_returns_empty_history(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['shoutbox:list']);

        $this->getJson('/api/v1/shoutbox')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);
    }
}
