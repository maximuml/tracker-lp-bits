<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\MeiliSearchRepositoryInterface;
use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Repositories\AuthRepository;
use App\Repositories\ExamRepository;
use App\Repositories\ForumRepository;
use App\Repositories\MeiliSearchRepository;
use App\Repositories\PageLayoutRepository;
use App\Repositories\PostRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\ToolRepository;
use App\Repositories\TorrentDownloadRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserModerationRepository;
use App\Repositories\UserRepository;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class RepositoryContractTest extends TestCase
{
    public function test_auth_repository_interface_binding(): void
    {
        $this->assertInstanceOf(AuthRepository::class, $this->app->make(AuthRepositoryInterface::class));
        $mock = Mockery::mock(AuthRepositoryInterface::class);
        $this->app->instance(AuthRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(AuthRepositoryInterface::class));
    }

    public function test_exam_repository_interface_binding(): void
    {
        $this->assertInstanceOf(ExamRepository::class, $this->app->make(ExamRepositoryInterface::class));
        $mock = Mockery::mock(ExamRepositoryInterface::class);
        $this->app->instance(ExamRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(ExamRepositoryInterface::class));
    }

    public function test_forum_repository_interface_binding(): void
    {
        $this->assertInstanceOf(ForumRepository::class, $this->app->make(ForumRepositoryInterface::class));
        $mock = Mockery::mock(ForumRepositoryInterface::class);
        $this->app->instance(ForumRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(ForumRepositoryInterface::class));
    }

    public function test_meili_search_repository_interface_binding(): void
    {
        $this->assertInstanceOf(MeiliSearchRepository::class, $this->app->make(MeiliSearchRepositoryInterface::class));
        $mock = Mockery::mock(MeiliSearchRepositoryInterface::class);
        $this->app->instance(MeiliSearchRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(MeiliSearchRepositoryInterface::class));
    }

    public function test_page_layout_repository_interface_binding(): void
    {
        $this->assertInstanceOf(PageLayoutRepository::class, $this->app->make(PageLayoutRepositoryInterface::class));
        $mock = Mockery::mock(PageLayoutRepositoryInterface::class);
        $this->app->instance(PageLayoutRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(PageLayoutRepositoryInterface::class));
    }

    public function test_post_repository_interface_binding(): void
    {
        $this->assertInstanceOf(PostRepository::class, $this->app->make(PostRepositoryInterface::class));
        $mock = Mockery::mock(PostRepositoryInterface::class);
        $this->app->instance(PostRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(PostRepositoryInterface::class));
    }

    public function test_search_box_repository_interface_binding(): void
    {
        $this->assertInstanceOf(SearchBoxRepository::class, $this->app->make(SearchBoxRepositoryInterface::class));
        $mock = Mockery::mock(SearchBoxRepositoryInterface::class);
        $this->app->instance(SearchBoxRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(SearchBoxRepositoryInterface::class));
    }

    public function test_tag_repository_interface_binding(): void
    {
        $this->assertInstanceOf(TagRepository::class, $this->app->make(TagRepositoryInterface::class));
        $mock = Mockery::mock(TagRepositoryInterface::class);
        $this->app->instance(TagRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(TagRepositoryInterface::class));
    }

    public function test_tool_repository_interface_binding(): void
    {
        $this->assertInstanceOf(ToolRepository::class, $this->app->make(ToolRepositoryInterface::class));
        $mock = Mockery::mock(ToolRepositoryInterface::class);
        $this->app->instance(ToolRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(ToolRepositoryInterface::class));
    }

    public function test_torrent_repository_interface_binding(): void
    {
        $this->assertInstanceOf(TorrentRepository::class, $this->app->make(TorrentRepositoryInterface::class));
        $mock = Mockery::mock(TorrentRepositoryInterface::class);
        $this->app->instance(TorrentRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(TorrentRepositoryInterface::class));
    }

    public function test_torrent_download_repository_interface_binding(): void
    {
        $this->assertInstanceOf(TorrentDownloadRepository::class, $this->app->make(TorrentDownloadRepositoryInterface::class));
        $mock = Mockery::mock(TorrentDownloadRepositoryInterface::class);
        $this->app->instance(TorrentDownloadRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(TorrentDownloadRepositoryInterface::class));
    }

    public function test_user_moderation_repository_interface_binding(): void
    {
        $this->assertInstanceOf(UserModerationRepository::class, $this->app->make(UserModerationRepositoryInterface::class));
        $mock = Mockery::mock(UserModerationRepositoryInterface::class);
        $this->app->instance(UserModerationRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(UserModerationRepositoryInterface::class));
    }

    public function test_user_repository_interface_binding(): void
    {
        $this->assertInstanceOf(UserRepository::class, $this->app->make(UserRepositoryInterface::class));
        $mock = Mockery::mock(UserRepositoryInterface::class);
        $this->app->instance(UserRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(UserRepositoryInterface::class));
    }
}
