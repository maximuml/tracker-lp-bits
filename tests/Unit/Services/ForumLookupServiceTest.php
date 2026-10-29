<?php

namespace Tests\Unit\Services;

use App\Repositories\ForumModRepository;
use App\Services\ForumLookupService;
use App\Support\Cache\NexusCache;
use Mockery;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class ForumLookupServiceTest extends TestCase
{
    public function test_pic_folder_builds_relative_path(): void
    {
        $service = new ForumLookupService(
            Mockery::mock(ForumModRepository::class),
            Mockery::mock(NexusCache::class),
        );

        $this->assertSame('pic/forum_pic/en', $service->picFolder('en'));
        $this->assertSame('pic/forum_pic/zh_CN', $service->picFolder('zh_CN'));
    }
}
