<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\CommentSection;
use App\Livewire\PeerList;
use App\Livewire\Shoutbox;
use App\Livewire\TorrentFileList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression: public component properties set at mount (torrent ids,
 * parent ids, limits, permission flags) must be #[Locked] — otherwise a
 * client can rewrite them in an update payload, bypassing the page-level
 * authorization that decided which torrent/entity to render.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class LockedPropertiesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_peer_list_torrent_id_is_locked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PeerList::class, ['torrentId' => 1])->set('torrentId', 999);
    }

    public function test_torrent_file_list_torrent_id_is_locked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(TorrentFileList::class, ['torrentId' => 1])->set('torrentId', 999);
    }

    public function test_shoutbox_limit_is_locked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Shoutbox::class, [
            'cardTitle' => '', 'autoRefreshLabel' => '', 'refreshSeconds' => '',
            'secondsLabel' => '', 'historyLabel' => '', 'canManage' => false,
            'clearConfirm' => '', 'clearLabel' => '', 'showHideTitle' => '',
        ])->set('limit', 1000000);
    }

    public function test_comment_section_parent_id_is_locked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(CommentSection::class, ['parentId' => 1])->set('parentId', 999);
    }
}
