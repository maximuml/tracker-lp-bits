<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\AuditStaffActions;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class AuditStaffActionsTest extends TestCase
{
    public function test_staff_only_paths_are_audited(): void
    {
        foreach (['modtask', 'settings', 'makepoll', 'faqmanage', 'staffpanel',
            'user-ban-log', 'fastdelete', 'docleanup', 'clearcache',
            'forummanage', 'moforums', 'donated'] as $path) {
            $this->assertTrue(AuditStaffActions::isAuditablePath($path), $path);
        }
    }

    public function test_moderation_subpaths_are_audited(): void
    {
        // comment/{id}/edit and /delete by staff ARE staff actions; only
        // the bare /comment store route is plain user activity.
        $this->assertTrue(AuditStaffActions::isAuditablePath('comment/12/delete'));
        $this->assertTrue(AuditStaffActions::isAuditablePath('comment/12/edit'));
        $this->assertFalse(AuditStaffActions::isAuditablePath('comment'));
    }

    public function test_user_activity_paths_are_skipped(): void
    {
        foreach (['takemessage', 'messages', 'usercp', 'usercp/security',
            'login', 'logout', 'index', 'upload', 'shoutbox', 'notifications',
            'friends', 'mybonus', 'attendance', 'report', 'invite'] as $path) {
            $this->assertFalse(AuditStaffActions::isAuditablePath($path), $path);
        }
    }

    public function test_infrastructure_prefixes_are_skipped(): void
    {
        foreach (['api/tokens', 'livewire/update', 'nexusphp/users', 'horizon/stats'] as $path) {
            $this->assertFalse(AuditStaffActions::isAuditablePath($path), $path);
        }
    }

    public function test_ajax_read_actions_skipped_mutations_audited(): void
    {
        $this->assertFalse(AuditStaffActions::isAuditablePath('ajax', 'getToastNotifications'));
        $this->assertFalse(AuditStaffActions::isAuditablePath('ajax', 'shoutboxPost'));
        $this->assertFalse(AuditStaffActions::isAuditablePath('ajax', 'consumeBenefit'));
        // Staff mutations via ajax.php stay auditable.
        $this->assertTrue(AuditStaffActions::isAuditablePath('ajax', 'clearShoutBox'));
        $this->assertTrue(AuditStaffActions::isAuditablePath('ajax', 'shoutboxDelete'));
        $this->assertTrue(AuditStaffActions::isAuditablePath('ajax', 'approval'));
    }
}
