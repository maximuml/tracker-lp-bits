<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TorrentOperationAction;
use App\Enums\UserClass;
use App\Exceptions\InsufficientPermissionException;
use App\Models\Torrent;
use App\Models\TorrentDenyReason;
use App\Models\TorrentOperationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Phase 10 regression: the torrent approval page renders as a standalone
 * modern page — no Layui assets/markup — with server-rendered deny reasons
 * and approval logs (usernames eager-loaded).
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class TorrentApprovalPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_staff_gets_standalone_modern_approval_page(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        TorrentDenyReason::query()->create(['name' => 'Bad description', 'priority' => 10]);
        TorrentOperationLog::query()->create([
            'uid' => $admin->id,
            'torrent_id' => $torrent->id,
            'action_type' => TorrentOperationAction::APPROVAL_ALLOW->value,
            'comment' => 'looks fine',
        ]);

        $response = $this->withNexusCookie($admin)
            ->get('/web/torrent-approval-page?torrent_id='.$torrent->id);

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringNotContainsString('layui', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
        $this->assertStringContainsString('data-theme="', $html);
        $this->assertStringContainsString('action="/web/torrent-approval"', $html);
        $this->assertStringContainsString('name="approval_status"', $html);
        $this->assertStringContainsString('name="torrent_id" value="'.$torrent->id.'"', $html);
        // Deny-reason chips and the server-rendered log row.
        $this->assertStringContainsString('Bad description', $html);
        $this->assertStringContainsString(e($admin->username), $html);
        $this->assertStringContainsString('looks fine', $html);
    }

    public function test_approval_page_paginates_logs(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        for ($i = 0; $i < 21; $i++) {
            TorrentOperationLog::query()->create([
                'uid' => $admin->id,
                'torrent_id' => $torrent->id,
                'action_type' => TorrentOperationAction::APPROVAL_ALLOW->value,
                'comment' => 'log '.$i,
            ]);
        }

        $page1 = $this->withNexusCookie($admin)
            ->get('/web/torrent-approval-page?torrent_id='.$torrent->id);
        $page1->assertOk();
        $html = (string) $page1->getContent();

        // Newest-first: page 1 shows logs 20..1, the oldest one paginates.
        $this->assertStringContainsString('log 20', $html);
        $this->assertStringContainsString('log 1', $html);
        $this->assertStringNotContainsString('>log 0<', $html);

        $page2 = $this->withNexusCookie($admin)
            ->get('/web/torrent-approval-page?torrent_id='.$torrent->id.'&page=2');
        $page2->assertOk();
        $this->assertStringContainsString('>log 0<', (string) $page2->getContent());
    }

    public function test_regular_user_is_denied(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        // The InsufficientPermissionException → permission-denied page
        // renderable only registers for real HTTP (runningInConsole skips
        // it), so the raw exception surfaces here — same as every other
        // assertCan-gated page under test.
        $this->withoutExceptionHandling();
        $this->expectException(InsufficientPermissionException::class);

        $this->withNexusCookie($user)
            ->get('/web/torrent-approval-page?torrent_id='.$torrent->id);
    }
}
