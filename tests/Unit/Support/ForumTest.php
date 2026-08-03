<?php

namespace Tests\Unit\Support;

use App\Support\Forum;
use PHPUnit\Framework\TestCase;

final class ForumTest extends TestCase
{
    public function test_pic_folder_builds_relative_path(): void
    {
        $this->assertSame('pic/forum_pic/en', Forum::picFolder('en'));
        $this->assertSame('pic/forum_pic/zh_CN', Forum::picFolder('zh_CN'));
    }
}
