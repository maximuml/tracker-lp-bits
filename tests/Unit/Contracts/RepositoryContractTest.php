<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\CleanupMonitorRepositoryInterface;
use App\Contracts\Repositories\ExamProgressCalculatorInterface;
use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\InfoRepositoryInterface;
use App\Contracts\Repositories\MeiliSearchRepositoryInterface;
use App\Contracts\Repositories\MysqlStatsRepositoryInterface;
use App\Contracts\Repositories\NotificationFeedRepositoryInterface;
use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Contracts\Repositories\ToptenRepositoryInterface;
use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\UserSearchRepositoryInterface;
use App\Repositories\AuthRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\CleanupMonitorRepository;
use App\Repositories\ExamProgressCalculator;
use App\Repositories\ExamRepository;
use App\Repositories\ForumRepository;
use App\Repositories\InfoRepository;
use App\Repositories\MeiliSearchRepository;
use App\Repositories\MysqlStatsRepository;
use App\Repositories\NotificationFeedRepository;
use App\Repositories\OfferCommentRepository;
use App\Repositories\OfferRepository;
use App\Repositories\OfferVoteRepository;
use App\Repositories\PageLayoutRepository;
use App\Repositories\PostRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\ShoutboxRepository;
use App\Repositories\TagRepository;
use App\Repositories\ToolRepository;
use App\Repositories\ToptenRepository;
use App\Repositories\TorrentAjaxRepository;
use App\Repositories\TorrentDownloadRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UsercpLookupRepository;
use App\Repositories\UsercpRepository;
use App\Repositories\UserModerationRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSearchRepository;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
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

    public function test_info_repository_interface_binding(): void
    {
        $this->assertInstanceOf(InfoRepository::class, $this->app->make(InfoRepositoryInterface::class));
        $mock = Mockery::mock(InfoRepositoryInterface::class);
        $this->app->instance(InfoRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(InfoRepositoryInterface::class));
    }

    public function test_meili_search_repository_interface_binding(): void
    {
        $this->assertInstanceOf(MeiliSearchRepository::class, $this->app->make(MeiliSearchRepositoryInterface::class));
        $mock = Mockery::mock(MeiliSearchRepositoryInterface::class);
        $this->app->instance(MeiliSearchRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(MeiliSearchRepositoryInterface::class));
    }

    public function test_mysql_stats_repository_interface_binding(): void
    {
        $this->assertInstanceOf(MysqlStatsRepository::class, $this->app->make(MysqlStatsRepositoryInterface::class));
        $mock = Mockery::mock(MysqlStatsRepositoryInterface::class);
        $this->app->instance(MysqlStatsRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(MysqlStatsRepositoryInterface::class));
    }

    public function test_offer_comment_repository_interface_binding(): void
    {
        $this->assertInstanceOf(OfferCommentRepository::class, $this->app->make(OfferCommentRepositoryInterface::class));
        $mock = Mockery::mock(OfferCommentRepositoryInterface::class);
        $this->app->instance(OfferCommentRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(OfferCommentRepositoryInterface::class));
    }

    public function test_offer_repository_interface_binding(): void
    {
        $this->assertInstanceOf(OfferRepository::class, $this->app->make(OfferRepositoryInterface::class));
        $mock = Mockery::mock(OfferRepositoryInterface::class);
        $this->app->instance(OfferRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(OfferRepositoryInterface::class));
    }

    public function test_offer_vote_repository_interface_binding(): void
    {
        $this->assertInstanceOf(OfferVoteRepository::class, $this->app->make(OfferVoteRepositoryInterface::class));
        $mock = Mockery::mock(OfferVoteRepositoryInterface::class);
        $this->app->instance(OfferVoteRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(OfferVoteRepositoryInterface::class));
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

    public function test_shoutbox_repository_interface_binding(): void
    {
        $this->assertInstanceOf(ShoutboxRepository::class, $this->app->make(ShoutboxRepositoryInterface::class));
        $mock = Mockery::mock(ShoutboxRepositoryInterface::class);
        $this->app->instance(ShoutboxRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(ShoutboxRepositoryInterface::class));
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

    public function test_topten_repository_interface_binding(): void
    {
        $this->assertInstanceOf(ToptenRepository::class, $this->app->make(ToptenRepositoryInterface::class));
        $mock = Mockery::mock(ToptenRepositoryInterface::class);
        $this->app->instance(ToptenRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(ToptenRepositoryInterface::class));
    }

    public function test_torrent_repository_interface_binding(): void
    {
        $this->assertInstanceOf(TorrentRepository::class, $this->app->make(TorrentRepositoryInterface::class));
        $mock = Mockery::mock(TorrentRepositoryInterface::class);
        $this->app->instance(TorrentRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(TorrentRepositoryInterface::class));
    }

    public function test_torrent_ajax_repository_interface_binding(): void
    {
        $this->assertInstanceOf(TorrentAjaxRepository::class, $this->app->make(TorrentAjaxRepositoryInterface::class));
        $mock = Mockery::mock(TorrentAjaxRepositoryInterface::class);
        $this->app->instance(TorrentAjaxRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(TorrentAjaxRepositoryInterface::class));
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


    public function test_category_repository_interface_binding(): void
    {
        $this->assertInstanceOf(CategoryRepository::class, $this->app->make(CategoryRepositoryInterface::class));
        $mock = Mockery::mock(CategoryRepositoryInterface::class);
        $this->app->instance(CategoryRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(CategoryRepositoryInterface::class));
    }

    public function test_cleanup_monitor_repository_interface_binding(): void
    {
        $this->assertInstanceOf(CleanupMonitorRepository::class, $this->app->make(CleanupMonitorRepositoryInterface::class));
        $mock = Mockery::mock(CleanupMonitorRepositoryInterface::class);
        $this->app->instance(CleanupMonitorRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(CleanupMonitorRepositoryInterface::class));
    }

    public function test_exam_progress_calculator_interface_binding(): void
    {
        $this->assertInstanceOf(ExamProgressCalculator::class, $this->app->make(ExamProgressCalculatorInterface::class));
        $mock = Mockery::mock(ExamProgressCalculatorInterface::class);
        $this->app->instance(ExamProgressCalculatorInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(ExamProgressCalculatorInterface::class));
    }

    public function test_notification_feed_repository_interface_binding(): void
    {
        $this->assertInstanceOf(NotificationFeedRepository::class, $this->app->make(NotificationFeedRepositoryInterface::class));
        $mock = Mockery::mock(NotificationFeedRepositoryInterface::class);
        $this->app->instance(NotificationFeedRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(NotificationFeedRepositoryInterface::class));
    }

    public function test_usercp_lookup_repository_interface_binding(): void
    {
        $this->assertInstanceOf(UsercpLookupRepository::class, $this->app->make(UsercpLookupRepositoryInterface::class));
        $mock = Mockery::mock(UsercpLookupRepositoryInterface::class);
        $this->app->instance(UsercpLookupRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(UsercpLookupRepositoryInterface::class));
    }

    public function test_user_search_repository_interface_binding(): void
    {
        $this->assertInstanceOf(UserSearchRepository::class, $this->app->make(UserSearchRepositoryInterface::class));
        $mock = Mockery::mock(UserSearchRepositoryInterface::class);
        $this->app->instance(UserSearchRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(UserSearchRepositoryInterface::class));
    }

    public function test_usercp_repository_interface_binding(): void
    {
        $this->assertInstanceOf(UsercpRepository::class, $this->app->make(UsercpRepositoryInterface::class));
        $mock = Mockery::mock(UsercpRepositoryInterface::class);
        $this->app->instance(UsercpRepositoryInterface::class, $mock);
        $this->assertSame($mock, $this->app->make(UsercpRepositoryInterface::class));
    }
>>>>>>> 66b13437 (refactor(repositories): extract contracts for 6 more final repos (W2-01/W2-02))
}
