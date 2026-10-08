<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Requests\DeleteMessageRequest;
use App\Http\Requests\StoreMessageRequest;

/**
 * Legacy-URI POST dispatcher registry: route URI → 308-forward rules for
 * the endpoints that were split into dedicated /web/* REST routes. Sibling
 * of LegacyAjaxRedirects — same idea, for the page-level dispatchers.
 *
 * Entry shape:
 * - 'target' => fixed URI every POST forwards to ('{input:name}' segments
 *   are filled from request input),
 * - 'request' => FormRequest class resolved before forwarding — the shim
 *   validated before redirecting historically, so resolution keeps the
 *   same validate-at-shim UX (target endpoints re-validate on arrival),
 * - 'rules' => ordered [matchers, URI] pairs tried first-to-match; each
 *   matcher is ['input:name' => value] and/or ['query:name' => value],
 * - 'default' => non-308 fallback when nothing matched (302),
 * - 'abort' => non-308 fallback rendered as an abort page with this text.
 */
final class LegacyPostRedirects
{
    /** @var array<string, array<string, mixed>> */
    private const MAP = [
        // Public group (routes/legacy/public.php)
        'faq' => ['target' => '/web/faq/submit'],
        'donate' => ['target' => '/web/donate/submit'],
        'complains' => [
            'rules' => [
                [['input:action' => 'new'], '/web/complains/new'],
                [['input:action' => 'reply'], '/web/complains/reply'],
                [['input:action' => 'answered'], '/web/complains/answered'],
                [['input:action' => 'unanswered'], '/web/complains/unanswered'],
            ],
            'abort' => 'Permission denied.',
        ],
        'bookmark' => ['target' => '/web/torrents/bookmark'],

        // Auth group (routes/legacy/auth.php)
        'mybonus' => [
            'rules' => [[['query:action' => 'exchange'], '/web/mybonus/exchange']],
            'default' => '/web/mybonus',
        ],
        'my_bonus' => [
            'rules' => [[['query:action' => 'exchange'], '/web/mybonus/exchange']],
            'default' => '/web/mybonus',
        ],
        'index' => ['target' => '/web/index/submit'],
        'messages' => [
            'rules' => [
                [['input:action' => 'moveordel'], '/web/messages/move-or-delete'],
                [['input:action' => 'editmailboxes2'], '/web/messages/mailboxes'],
                [['input:action' => 'deletemessage'], '/web/messages/delete'],
            ],
            'default' => '/web/messages',
        ],
        'getrss' => ['target' => '/web/rss/generate'],
        'invite' => ['target' => '/web/invites/submit'],
        'makepoll' => ['target' => '/web/polls/create'],
        'polloverview' => ['target' => '/web/polls/overview'],
        'attendance' => ['target' => '/web/user/attendance'],
        'takemessage' => ['target' => '/web/messages/send', 'request' => StoreMessageRequest::class],
        'deletemessage' => ['target' => '/web/messages/delete/{input:type}', 'request' => DeleteMessageRequest::class],
        'report' => ['target' => '/web/reports/create'],
        'modtask' => ['target' => '/web/staff/modtask'],
        'staffmess' => ['target' => '/web/staffmess/submit'],
        'takestaffmess' => ['target' => '/web/staffmess/send'],
        'contactstaff' => ['target' => '/web/contactstaff/submit'],
        'takecontact' => ['target' => '/web/contactstaff/send'],
        'modrules' => ['target' => '/web/staff/modrules'],
        'user-ban-log' => ['target' => '/web/admin/user-ban-log'],
        'takeflush' => ['target' => '/web/torrents/flush'],
        'takereseed' => ['target' => '/web/torrents/reseed'],
        'clearcache' => ['target' => '/web/system/clear-cache'],
        'fastdelete' => ['target' => '/web/torrents/fast-delete'],
        'donated' => ['target' => '/web/info/donated'],
        'faqmanage' => ['target' => '/web/faq/manage'],
        'faqactions' => ['target' => '/web/faq/actions'],
        'attachment' => ['target' => '/web/attachments/upload'],
        'notifications' => ['target' => '/web/notifications/mark-read'],
        'settings' => ['target' => '/web/settings/submit'],
        'freeleech' => ['target' => '/web/bonus/freeleech'],
        'magic' => ['target' => '/web/bonus/magic'],
        'takeamountupload' => ['target' => '/web/system/amount-upload'],
        'takeinvite' => ['target' => '/web/invites/send'],
        'takeupdate' => ['target' => '/web/system/update'],
        'docleanup' => ['target' => '/web/system/cleanup'],
        'location' => ['target' => '/web/system/location'],
        'preview' => ['target' => '/web/preview'],
        'mailtest' => ['target' => '/web/system/mail-test'],
        'reset' => ['target' => '/web/admin/users/reset'],
        'self-enable' => ['target' => '/web/admin/users/self-enable'],
        'unco' => ['target' => '/web/admin/users/unco'],
        'adduser' => ['target' => '/web/admin/users/add'],
        'bitbucketlog' => ['target' => '/web/admin/bitbucket-log'],
        'delete' => ['target' => '/web/torrents/delete'],
        'downloadnotice' => ['target' => '/web/torrents/download-notice'],
        'thanks' => ['target' => '/web/torrents/thanks'],
        'take-increment-bulk' => ['target' => '/web/system/increment-bulk'],
        'testip' => ['target' => '/web/system/test-ip'],

        // Stray dispatcher living in routes/web.php
        'usercp' => [
            'rules' => [
                [['input:action' => 'personal', 'input:type' => 'save'], '/web/usercp/personal'],
                [['input:action' => 'forum', 'input:type' => 'save'], '/web/usercp/forum'],
                [['input:action' => 'tracker', 'input:type' => 'save'], '/web/usercp/tracker'],
                [['input:action' => 'security', 'input:type' => 'confirm'], '/web/usercp/security/confirm'],
                [['input:action' => 'security', 'input:type' => 'save'], '/web/usercp/security'],
            ],
            'default' => '/usercp',
        ],
    ];

    /** @return array<string, mixed>|null */
    public static function entry(string $routeUri): ?array
    {
        return self::MAP[$routeUri] ?? null;
    }
}
