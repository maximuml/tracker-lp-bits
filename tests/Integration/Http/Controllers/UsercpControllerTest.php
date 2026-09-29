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

        $result = $controller->settings($request);

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
        $user = User::factory()->create(['class' => 1, 'notifs' => '[cat402][med3][pm]']);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/tracker', 'POST', [
            'cat401' => '0',
            'med1' => '0',
            'sta2' => '0',
            'pmnotif' => 'yes',
            'torrentsperpage' => 25,
            'pmnum' => 20,
            'sbnum' => 70,
            'sbrefresh' => 120,
        ]);
        app()->instance('request', $request);

        $result = $controller->tracker($request);

        $this->assertSame(0, $result['ret']);

        $notifs = (string) DB::table('users')->where('id', $user->id)->value('notifs');
        $this->assertStringContainsString('[cat401]', $notifs);
        $this->assertStringContainsString('[med1]', $notifs);
        $this->assertStringContainsString('[sta2]', $notifs);
        $this->assertStringContainsString('[pm]', $notifs);
        $this->assertStringNotContainsString('[cat402]', $notifs);
        $this->assertStringNotContainsString('[med3]', $notifs);
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
        $this->assertSame(0, $controller->settings($request)['ret']);
        $this->assertSame('https://example.com/a.jpg', DB::table('users')->where('id', $user->id)->value('avatar'));

        // Explicit empty field clears the stored avatar.
        $request = Request::create('/api/usercp/settings', 'POST', [
            'avatar' => '',
        ]);
        app()->instance('request', $request);
        $this->assertSame(0, $controller->settings($request)['ret']);
        $this->assertSame('', (string) DB::table('users')->where('id', $user->id)->value('avatar'));
    }
}
