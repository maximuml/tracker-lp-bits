<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\User;
use App\Repositories\ForumModRepository;
use App\Services\ForumLookupService;
use App\Support\Cache\NexusCache;
use App\Support\PageState;
use App\Support\State\ForumState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumLookupServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        ForumState::instance()->reset();
        PageState::instance()->setLangDir('en');
    }

    public function test_pic_folder_builds_relative_path(): void
    {
        $service = new ForumLookupService(
            Mockery::mock(ForumModRepository::class),
            Mockery::mock(NexusCache::class),
            new ForumState,
        );

        $this->assertSame('pic/forum_pic/en', $service->picFolder('en'));
        $this->assertSame('pic/forum_pic/zh_CN', $service->picFolder('zh_CN'));
    }

    public function test_pic_folder_defaults_to_current_lang_dir(): void
    {
        $service = new ForumLookupService(
            Mockery::mock(ForumModRepository::class),
            Mockery::mock(NexusCache::class),
            new ForumState,
        );

        $this->assertSame('pic/forum_pic/en', $service->picFolder());
    }

    public function test_moderators_returns_plain_names(): void
    {
        $modA = User::factory()->create(['username' => 'alpha_mod']);
        $modB = User::factory()->create(['username' => 'beta_mod']);
        DB::table('forummods')->insert([
            ['forumid' => 5001, 'userid' => $modA->id],
            ['forumid' => 5001, 'userid' => $modB->id],
            ['forumid' => 5002, 'userid' => $modB->id],
        ]);

        $service = $this->app->make(ForumLookupService::class);

        $plain = $service->moderators(5001);

        $this->assertStringContainsString('alpha_mod', $plain);
        $this->assertStringContainsString('beta_mod', $plain);
        $this->assertStringNotContainsString('<', $plain);
        $this->assertSame('beta_mod', $service->moderators(5002));
    }

    public function test_moderators_html_mode_returns_markup(): void
    {
        $mod = User::factory()->create(['username' => 'html_mod']);
        DB::table('forummods')->insert(['forumid' => 5003, 'userid' => $mod->id]);

        $service = $this->app->make(ForumLookupService::class);

        $html = $service->moderators(5003, false);

        $this->assertStringContainsString('html_mod', $html);
        $this->assertStringContainsString('<', $html);
    }

    public function test_moderators_unknown_forum_returns_empty(): void
    {
        $service = $this->app->make(ForumLookupService::class);

        $this->assertSame('', $service->moderators(PHP_INT_MAX));
    }

    public function test_moderators_uses_cached_array_when_present(): void
    {
        $mod = User::factory()->create(['username' => 'cached_mod']);
        $cache = $this->app->make(NexusCache::class);
        $cache->put('forum_moderator_array', [5004 => [$mod->id]], 3600);

        $service = $this->app->make(ForumLookupService::class);

        $this->assertSame('cached_mod', $service->moderators(5004));
    }
}
