<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UsercpController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

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
            'acceptpms' => 'friends',
            'deletepms' => true,
            'savepms' => true,
            'commentpm' => 'yes',
            'gender' => 'Male',
            'info' => 'Updated info',
        ]);
        app()->instance('request', $request);

        $result = $controller->settings($request);

        $this->assertSame(0, $result['ret']);
        $this->assertNotEmpty($result['data']);

        $updated = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame('friends', $updated->acceptpms);
        $this->assertSame('Male', $updated->gender);
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
            'clicktopic' => 'lastpage',
            'signature' => 'My signature',
        ]);
        app()->instance('request', $request);

        $result = $controller->forum($request);

        $this->assertSame(0, $result['ret']);
        $this->assertNotEmpty($result['data']);

        $updated = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(25, (int) $updated->topicsperpage);
        $this->assertSame(30, (int) $updated->postsperpage);
        $this->assertSame('lastpage', $updated->clicktopic);
    }

    public function test_tracker_updates_tracker_settings(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/tracker', 'POST', [
            'torrentsperpage' => 50,
            'timetype' => 'timeadded',
            'appendsticky' => 'yes',
            'appendnew' => 'yes',
            'appendpromotion' => 'word',
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
            'fontsize' => 'large',
        ]);
        app()->instance('request', $request);

        $result = $controller->tracker($request);

        $this->assertSame(0, $result['ret']);
        $this->assertNotEmpty($result['data']);

        $updated = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(50, (int) $updated->torrentsperpage);
        $this->assertSame('timeadded', $updated->timetype);
        $this->assertSame('large', $updated->fontsize);
    }

    public function test_security_updates_security_settings(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user);

        $controller = app(UsercpController::class);
        $request = Request::create('/api/usercp/security', 'POST', [
            'current_password' => '123456',
            'privacy' => 'strong',
        ]);
        app()->instance('request', $request);

        $result = $controller->security($request);

        $this->assertSame(0, $result['ret']);
        $this->assertNotEmpty($result['data']);

        $updated = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame('strong', $updated->privacy);
    }
}
