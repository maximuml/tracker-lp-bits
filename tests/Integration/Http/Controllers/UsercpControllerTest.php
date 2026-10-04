<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Enums\UserGender;
use App\Enums\UserTimeType;
use App\Http\Controllers\UsercpController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class UsercpControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_settings_updates_personal_settings(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/settings', 'POST', [
            'parked' => 'yes',
            'acceptpms' => 1,
            'deletepms' => true,
            'savepms' => true,
            'commentpm' => 'yes',
            'gender' => 0,
            'info' => 'Updated info',
        ]);
        app()->instance('request', $request);

        $result = $controller->settingsPost($request);

        $this->assertSame(0, $result['ret']);
        $this->assertNotEmpty($result['data']);

        $updated = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(1, (int) $updated->acceptpms);
        $this->assertSame(UserGender::MALE->value, (int) $updated->gender);
    }

    public function test_forum_updates_forum_settings(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/forum', 'POST', [
            'topicsperpage' => 25,
            'postsperpage' => 30,
            'avatars' => 'yes',
            'signatures' => 'yes',
            'clicktopic' => 1,
            'signature' => 'My signature',
        ]);
        app()->instance('request', $request);

        $result = $controller->forum($request);

        $this->assertSame(0, $result['ret']);
        $this->assertNotEmpty($result['data']);

        $updated = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(25, (int) $updated->topicsperpage);
        $this->assertSame(30, (int) $updated->postsperpage);
        $this->assertSame(1, (int) $updated->clicktopic);
    }

    public function test_tracker_updates_tracker_settings(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/tracker', 'POST', [
            'torrentsperpage' => 50,
            'timetype' => 0,
            'appendsticky' => 'yes',
            'appendnew' => 'yes',
            'appendpromotion' => 1,
            'appendpicked' => 'yes',
            'dlicon' => 'yes',
            'bmicon' => 'yes',
            'showcomnum' => 'yes',
            'showdescription' => 'yes',
            'smalldescr' => 'yes',
            'showcomment' => 'yes',
            'pmnum' => 20,
            'sbnum' => 70,
            'sbrefresh' => 120,
            'fontsize' => 2,
        ]);
        app()->instance('request', $request);

        $result = $controller->tracker($request);

        $this->assertSame(0, $result['ret']);
        $this->assertNotEmpty($result['data']);

        $updated = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(50, (int) $updated->torrentsperpage);
        $this->assertSame(UserTimeType::TIMEADDED->value, (int) $updated->timetype);
        $this->assertSame(2, (int) $updated->fontsize);
    }

    public function test_security_updates_security_settings(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/security', 'POST', [
            'current_password' => '123456',
            'privacy' => 0,
        ]);
        app()->instance('request', $request);

        $result = $controller->security($request);

        $this->assertSame(0, $result['ret']);
        $this->assertNotEmpty($result['data']);

        $updated = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(0, (int) $updated->privacy);
    }

    /**
     * The category browse-notification grid emits checkboxes with value="0":
     * presence in POST means checked (legacy isset() semantics). Previously
     * the collector required === 'yes', so every save wiped cat/med/sta prefs.
     */
    public function test_tracker_collects_category_checkboxes_by_presence(): void
    {
        // Collect whichever taxonomy prefixes this seed provides — media/
        // standards are empty in some environments, categories always exist.
        $prefixedIds = [];
        foreach (['categories' => 'cat', 'media' => 'med', 'standards' => 'sta'] as $table => $prefix) {
            $id = DB::table($table)->min('id');
            if ($id !== null) {
                $prefixedIds["{$prefix}{$id}"] = true;
            }
        }
        $this->assertNotEmpty($prefixedIds);

        $user = User::factory()->create(['class' => 1, 'notifs' => '[pm]']);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/tracker', 'POST', array_merge(
            array_fill_keys(array_keys($prefixedIds), '0'),
            [
                'pmnotif' => 'yes',
                'torrentsperpage' => 25,
                'pmnum' => 20,
                'sbnum' => 70,
                'sbrefresh' => 120,
            ],
        ));
        app()->instance('request', $request);

        $result = $controller->tracker($request);

        $this->assertSame(0, $result['ret']);

        $notifs = (string) DB::table('users')->where('id', $user->id)->value('notifs');
        foreach (array_keys($prefixedIds) as $key) {
            $this->assertStringContainsString("[{$key}]", $notifs);
        }
        $this->assertStringContainsString('[pm]', $notifs);
    }

    /**
     * ttlastpost is a plain checkbox: absent means unchecked → write 'no'.
     */
    public function test_forum_unchecked_ttlastpost_disables_showlastpost(): void
    {
        $user = User::factory()->create(['class' => 1, 'showlastpost' => 1]);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/forum', 'POST', [
            'topicsperpage' => 15,
            'postsperpage' => 25,
            'signature' => 'sig',
        ]);
        app()->instance('request', $request);

        $result = $controller->forum($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(0, (int) DB::table('users')->where('id', $user->id)->value('showlastpost'));
    }

    public function test_personal_empty_avatar_clears_and_invalid_keeps(): void
    {
        $user = User::factory()->create(['class' => 1, 'avatar' => 'https://example.com/a.jpg']);
        $this->actingAs($user);

        $controller = app(UsercpController::class);

        // Invalid relative path — current avatar kept, not written over.
        $request = Request::create('/api/usercp/settings', 'POST', [
            'avatar' => 'not/a/url.jpg',
        ]);
        app()->instance('request', $request);
        $this->assertSame(0, $controller->settingsPost($request)['ret']);
        $this->assertSame('https://example.com/a.jpg', DB::table('users')->where('id', $user->id)->value('avatar'));

        // Empty field + savatar echoing the current URL (what the real form
        // posts when the text input is emptied) — still clears.
        $request = Request::create('/api/usercp/settings', 'POST', [
            'avatar' => '',
            'savatar' => 'https://example.com/a.jpg',
        ]);
        app()->instance('request', $request);
        $this->assertSame(0, $controller->settingsPost($request)['ret']);
        $this->assertSame('', (string) DB::table('users')->where('id', $user->id)->value('avatar'));
    }

    public function test_personal_savatar_pick_applies_when_text_empty(): void
    {
        $user = User::factory()->create(['class' => 1, 'avatar' => 'https://example.com/a.jpg']);
        $this->actingAs($user);

        $controller = app(UsercpController::class);

        // Empty text field + a savatar value different from the stored
        // avatar is a real pick (gallery option or "Nothing" reset).
        $request = Request::create('/api/usercp/settings', 'POST', [
            'avatar' => '',
            'savatar' => 'http://localhost/pic/default_avatar.png',
        ]);
        app()->instance('request', $request);
        $this->assertSame(0, $controller->settingsPost($request)['ret']);
        $this->assertSame('http://localhost/pic/default_avatar.png', DB::table('users')->where('id', $user->id)->value('avatar'));
    }
}
