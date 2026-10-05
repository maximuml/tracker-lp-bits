<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserClass;
use App\Models\User;
use App\Support\Environment;
use App\Support\Log;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LaravelLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff audit trail: logs every mutating request performed by a
 * moderator-class user into sitelog (security_level=mod), excluding the
 * everyday user endpoints (commenting, PMs, shoutbox, …). Complements the
 * hand-written Log::write calls — those stay for their rich details, this
 * catches the staff actions that never log anything.
 */
final class AuditStaffActions
{
    /** Exact POST paths that are normal user activity, not staff actions. */
    private const USER_EXACT_PATHS = [
        'comment', 'shoutbox', 'shoutbox_post', 'notifications',
        'index', 'details', 'offers', 'upload', 'getrss',
        'viewmessage', 'idea', 'opinion', 'funbox', 'viewnfo',
        'userdetails', 'search', 'poll',
        // REST endpoints that replaced self-activity /ajax actions —
        // mirrors AJAX_SKIP_ACTIONS so the audit decision stays identical.
        'web/notifications/feed', 'web/offers/show', 'web/torrents/approval-modal',
        'web/benefits/consume', 'web/attendance/retroactive',
        'web/shoutbox/post', 'web/shoutbox/react',
        'web/friends/add', 'web/friends/delete',
        // Renamed take*/mailbox endpoints — user-self activity that the
        // legacy prefixes (takemessage, deletemessage, messages, usercp,
        // offers, mybonus, takecontact, takestaffmess, contactstaff,
        // staffmess) already excluded.
        'web/messages/send', 'web/messages/delete',
        'web/messages/delete/in', 'web/messages/delete/out',
        'web/messages/move-or-delete', 'web/messages/mailboxes',
        'web/staffmess/send', 'web/contactstaff/send',
        'web/usercp/theme', 'web/usercp/logout-all', 'web/usercp/personal',
        'web/usercp/forum', 'web/usercp/tracker', 'web/usercp/security/confirm',
        'web/offers/create', 'web/offers/allow', 'web/offers/finish',
        'web/offers/delete', 'web/offers/edit',
        'web/mybonus/exchange',
        'web/torrents/flush', 'web/invites/send',
    ];

    /** First-segment prefixes where every sub-path is user activity. */
    private const USER_PATH_PREFIXES = [
        'takemessage', 'deletemessage', 'messages', 'friends', 'bookmark',
        'thanks', 'mybonus', 'my_bonus', 'magic', 'freeleech', 'attendance',
        'takeconfirm', 'report', 'usercp', 'userscp', 'logout', 'login',
        'recover', 'signup', 'invite', 'attachment', 'freetorrent',
        'bitbucket-upload', 'takeflush', 'love', 'promotion_link',
        'saveconninfo', 'cheaters', 'claim', 'subs', 'clear_cache_user',
        'take_edit', 'takecontact', 'takestaffmess', 'contactstaff',
        'staffmess', 'comment_thanks', 'theme',
    ];

    /** ajax.php actions that are read-only or user-self — not staff actions. */
    private const AJAX_SKIP_ACTIONS = [
        'getToastNotifications', 'getOffer', 'approvalModal',
        'shoutboxPost', 'shoutboxReact', 'consumeBenefit',
        'attendanceRetroactive',
    ];

    /** Params never written to the log (credentials, tokens, signatures). */
    private const SENSITIVE_PARAMS = [
        'password', 'password2', 'passkey', 'secret', 'auth_key', 'signature',
        'token', '_token', 'pwd', 'passhash', 'chpassword', 'chpasswordagain',
        'iv', 'qr', 'totp', 'csrf', 'csrf_token',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $response;
        }

        $user = $request->user() ?? $request->user('nexus-web');
        if (! $user instanceof User) {
            return $response;
        }

        if ((int) $user->class < UserClass::MODERATOR->value) {
            return $response;
        }

        $path = ltrim((string) $request->path(), '/');
        if (! self::isAuditablePath($path, (string) $request->input('action'))) {
            return $response;
        }

        $this->writeLog($request, $response, $user, $path);

        return $response;
    }

    /**
     * Whether a mutating request to $path counts as a staff action worth
     * auditing. Pure and static so the decision matrix is unit-testable.
     */
    public static function isAuditablePath(string $path, string $ajaxAction = ''): bool
    {
        $path = ltrim($path, '/');
        $first = explode('/', $path)[0];

        if (\in_array($path, self::USER_EXACT_PATHS, true)
            || \in_array($first, self::USER_PATH_PREFIXES, true)
            || str_starts_with($path, 'api/')
            || str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'nexusphp/')
            || str_starts_with($path, 'horizon/')
        ) {
            return false;
        }

        if ($path === 'ajax' && \in_array($ajaxAction, self::AJAX_SKIP_ACTIONS, true)) {
            return false;
        }

        return true;
    }

    private function writeLog(Request $request, Response $response, User $user, string $path): void
    {
        $detail = sprintf(
            'STAFF %s /%s by %s',
            $request->method(),
            $path,
            $user->username,
        );

        $params = self::summarizeParams($request);
        if ($params !== '') {
            $detail .= ' {'.$params.'}';
        }

        $detail .= sprintf(' -> %s', $response->getStatusCode());

        if (Environment::isTesting()) {
            return;
        }

        try {
            Log::write($detail, 'mod', (int) $user->id);
        } catch (\Throwable $e) {
            // Auditing must never break the request itself.
            LaravelLog::warning('AuditStaffActions: '.$e->getMessage());
        }
    }

    /**
     * Flatten request params to a compact "key=value" list with secrets
     * removed; shows which object/action was targeted without leaking data.
     * Public so the redaction rules are unit-testable.
     */
    public static function summarizeParams(Request $request): string
    {
        $params = [];

        foreach (array_keys($request->all()) as $key) {
            if (\in_array(strtolower((string) $key), self::SENSITIVE_PARAMS, true)) {
                continue;
            }
            $value = $request->input($key);
            if (is_scalar($value) || $value === null) {
                $v = mb_substr((string) $value, 0, 40);
                $params[] = $key.'='.$v;
            } else {
                $params[] = $key.'=[complex]';
            }
            if (count($params) >= 12) {
                $params[] = '…';
                break;
            }
        }

        return implode(', ', $params);
    }
}
