<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\ExamUserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\OverForumController;
use App\Http\Controllers\PeerController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SnatchController;
use App\Http\Controllers\ThankController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\UserController;
use Tests\TestCase;

/**
 * Wave 5 Step 36: dead REST stubs removed.
 *
 * Verifies that:
 * - No controller method returns abort(501, 'Not implemented')
 * - No controller method returns new Response('', 204) or response('')
 * - Routes that pointed to stub methods are removed
 * - Real API routes still work
 */
final class DeadRestStubsTest extends TestCase
{
    /**
     * No abort(501, 'Not implemented') remains in any controller.
     */
    public function test_no_not_implemented_stubs_in_controllers(): void
    {
        $controllersDir = app_path('Http/Controllers');
        $found = [];
        foreach (glob($controllersDir.'/*.php') as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, "abort(501, 'Not implemented')")) {
                $found[] = basename($file);
            }
        }
        $this->assertEmpty($found, 'Controllers still containing abort(501) stubs: '.implode(', ', $found));
    }

    /**
     * No new Response('', 204) stubs remain in controllers.
     */
    public function test_no_empty_204_response_stubs(): void
    {
        $controllersDir = app_path('Http/Controllers');
        $found = [];
        foreach (glob($controllersDir.'/*.php') as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, "new Response('', 204)")) {
                $found[] = basename($file);
            }
        }
        $this->assertEmpty($found, 'Controllers still containing new Response("", 204) stubs: '.implode(', ', $found));
    }

    /**
     * emailGateway method is removed from TorrentDownloadController.
     */
    public function test_email_gateway_method_removed(): void
    {
        $reflection = new \ReflectionClass(TorrentDownloadController::class);
        $this->assertFalse($reflection->hasMethod('emailGateway'), 'emailGateway method should be removed');
    }

    /**
     * update and destroy methods are removed from TorrentController.
     */
    public function test_torrent_controller_update_destroy_removed(): void
    {
        $reflection = new \ReflectionClass(TorrentController::class);
        $this->assertFalse($reflection->hasMethod('update'), 'TorrentController::update should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'TorrentController::destroy should be removed');
    }

    /**
     * Stub methods are removed from PeerController (store, show, update, destroy).
     */
    public function test_peer_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(PeerController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertFalse($reflection->hasMethod('store'), 'store stub should be removed');
        $this->assertFalse($reflection->hasMethod('show'), 'show stub should be removed');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'destroy stub should be removed');
    }

    /**
     * Stub methods are removed from FileController (store, show, update, destroy).
     */
    public function test_file_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(FileController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertFalse($reflection->hasMethod('store'), 'store stub should be removed');
        $this->assertFalse($reflection->hasMethod('show'), 'show stub should be removed');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'destroy stub should be removed');
    }

    /**
     * Stub methods removed from ThankController (show, update, destroy), store kept.
     */
    public function test_thank_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(ThankController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertTrue($reflection->hasMethod('store'), 'store should remain (real method)');
        $this->assertFalse($reflection->hasMethod('show'), 'show stub should be removed');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'destroy stub should be removed');
    }

    /**
     * Stub methods removed from SnatchController (store, show, update, destroy).
     */
    public function test_snatch_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(SnatchController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertFalse($reflection->hasMethod('store'), 'store stub should be removed');
        $this->assertFalse($reflection->hasMethod('show'), 'show stub should be removed');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'destroy stub should be removed');
    }

    /**
     * Stub methods removed from RewardController (show, update, destroy), store kept.
     */
    public function test_reward_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(RewardController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertTrue($reflection->hasMethod('store'), 'store should remain (real method)');
        $this->assertFalse($reflection->hasMethod('show'), 'show stub should be removed');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'destroy stub should be removed');
    }

    /**
     * Stub methods removed from UserController (update, destroy), index/store/show kept.
     */
    public function test_user_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(UserController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertTrue($reflection->hasMethod('store'), 'store should remain (real method)');
        $this->assertTrue($reflection->hasMethod('show'), 'show should remain (real method)');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'destroy stub should be removed');
    }

    /**
     * Stub methods removed from OverForumController (store, show, update, destroy).
     */
    public function test_over_forum_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(OverForumController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertFalse($reflection->hasMethod('store'), 'store stub should be removed');
        $this->assertFalse($reflection->hasMethod('show'), 'show stub should be removed');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'destroy stub should be removed');
    }

    /**
     * Stub methods removed from ExamUserController (show, update), store/destroy kept.
     */
    public function test_exam_user_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(ExamUserController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertTrue($reflection->hasMethod('store'), 'store should remain (real method)');
        $this->assertFalse($reflection->hasMethod('show'), 'show stub should be removed');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertTrue($reflection->hasMethod('destroy'), 'destroy should remain (real method)');
    }

    /**
     * Stub methods removed from SettingController (show, update, destroy), store kept.
     */
    public function test_setting_controller_stubs_removed(): void
    {
        $reflection = new \ReflectionClass(SettingController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'index should remain');
        $this->assertTrue($reflection->hasMethod('store'), 'store should remain (real method)');
        $this->assertFalse($reflection->hasMethod('show'), 'show stub should be removed');
        $this->assertFalse($reflection->hasMethod('update'), 'update stub should be removed');
        $this->assertFalse($reflection->hasMethod('destroy'), 'destroy stub should be removed');
    }

    /**
     * The email-gateway route is removed.
     */
    public function test_email_gateway_route_removed(): void
    {
        $routes = app()->routes->get('GET') ?? [];
        foreach ($routes as $uri => $route) {
            $this->assertStringNotContainsString('email-gateway', $uri, 'email-gateway route should be removed');
        }
    }
}
