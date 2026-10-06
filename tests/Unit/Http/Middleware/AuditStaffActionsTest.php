<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Enums\UserClass;
use App\Http\Middleware\AuditStaffActions;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class AuditStaffActionsTest extends TestCase
{
    private function handle(Request $request, ?User $user): Response
    {
        if ($user !== null) {
            Auth::guard('nexus-web')->setUser($user);
        }

        return (new AuditStaffActions)->handle($request, fn () => new Response('ok'));
    }

    private function userOfClass(int $class): User
    {
        $user = new User;
        $user->id = 1;
        $user->username = 'tester';
        $user->class = $class;

        return $user;
    }

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

    public function test_rest_ajax_endpoints_keep_ajax_audit_semantics(): void
    {
        // Self-activity endpoints mirror the old AJAX_SKIP_ACTIONS entries.
        foreach (['web/notifications/feed', 'web/offers/show', 'web/torrents/approval-modal',
            'web/benefits/consume', 'web/attendance/retroactive',
            'web/shoutbox/post', 'web/shoutbox/react',
            'web/friends/add', 'web/friends/delete'] as $path) {
            $this->assertFalse(AuditStaffActions::isAuditablePath($path), $path);
        }
        // Mutations that were audited via /ajax stay audited under /web/*.
        foreach (['web/torrent-approval', 'web/token/add', 'web/token/del',
            'web/users/leech-warn/remove', 'web/hit-and-runs/remove', 'web/tasks/claim',
            'web/shoutbox/clear', 'web/shoutbox/edit', 'web/shoutbox/delete',
            'web/news/add', 'web/news/edit', 'web/news/delete'] as $path) {
            $this->assertTrue(AuditStaffActions::isAuditablePath($path), $path);
        }
    }

    public function test_rest_user_endpoints_keep_legacy_skip_semantics(): void
    {
        // Renamed take*/page-dispatcher endpoints stay user-self activity:
        // their legacy URIs were listed in USER_PATH_PREFIXES.
        foreach (['web/messages/send', 'web/messages/delete',
            'web/messages/delete/in', 'web/messages/delete/out',
            'web/messages/move-or-delete', 'web/messages/mailboxes',
            'web/staffmess/send', 'web/contactstaff/send',
            'web/usercp/theme', 'web/usercp/logout-all', 'web/usercp/personal',
            'web/usercp/forum', 'web/usercp/tracker', 'web/usercp/security/confirm',
            'web/offers/create', 'web/offers/allow', 'web/offers/finish',
            'web/offers/delete', 'web/offers/edit',
            'web/mybonus/exchange',
            'web/torrents/flush', 'web/invites/send',
            'web/torrents/thanks', 'web/bonus/magic',
            'web/bonus/freeleech', 'web/user/attendance',
            'web/reports/create', 'web/attachments/upload',
            'web/staffmess/submit', 'web/contactstaff/submit',
            'web/torrents/bookmark', 'web/invites/submit'] as $path) {
            $this->assertFalse(AuditStaffActions::isAuditablePath($path), $path);
        }
    }

    public function test_get_requests_never_audited(): void
    {
        $response = $this->handle(
            Request::create('/modtask', 'GET'),
            $this->userOfClass(UserClass::MODERATOR->value),
        );
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_guest_and_non_staff_posts_pass_through(): void
    {
        $staff = $this->userOfClass(UserClass::MODERATOR->value);
        $user = $this->userOfClass(UserClass::USER->value);

        $this->assertSame(200, $this->handle(Request::create('/modtask', 'POST'), null)->getStatusCode());
        $this->assertSame(200, $this->handle(Request::create('/modtask', 'POST'), $user)->getStatusCode());
        $this->assertSame(200, $this->handle(Request::create('/modtask', 'POST'), $staff)->getStatusCode());
    }

    public function test_summarize_params_redacts_secrets_and_truncates(): void
    {
        $request = Request::create('/settings', 'POST', [
            'name' => 'value',
            'password' => 'hunter2',
            'passkey' => 'deadbeef',
            '_token' => 'csrf',
            'nested' => ['a' => 1],
            'long' => str_repeat('x', 100),
        ]);

        $out = AuditStaffActions::summarizeParams($request);

        $this->assertStringContainsString('name=value', $out);
        $this->assertStringNotContainsString('hunter2', $out);
        $this->assertStringNotContainsString('deadbeef', $out);
        $this->assertStringNotContainsString('csrf', $out);
        $this->assertStringContainsString('nested=[complex]', $out);
        $this->assertStringContainsString('long='.str_repeat('x', 40), $out);
        $this->assertStringNotContainsString(str_repeat('x', 41), $out);
    }
}
