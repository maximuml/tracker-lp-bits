<?php

declare(strict_types=1);

namespace App\Support;

/**
 * action-string → REST URI map for /ajax actions that were migrated to
 * their own endpoints. The legacy dispatcher 308-redirects them (status
 * 308 preserves method + body, so a `{action, params}` POST replays
 * byte-identically against the target; the target FormRequest flattens
 * the envelope via FlattensAjaxEnvelope). Actions absent from the map
 * still dispatch through AjaxService until their group migrates
 * (all groups migrated — the map is exhaustive).
 */
final class AjaxRedirects
{
    /** @var array<string, string> */
    private const MAP = [
        'attendanceRetroactive' => '/web/attendance/retroactive',
        'removeUserLeechWarn' => '/web/users/leech-warn/remove',
        'getOffer' => '/web/offers/show',
        'approvalModal' => '/web/torrents/approval-modal',
        'approval' => '/web/torrent-approval',
        'removeHitAndRun' => '/web/hit-and-runs/remove',
        'consumeBenefit' => '/web/benefits/consume',
        'claimTask' => '/web/tasks/claim',
        'addToken' => '/web/token/add',
        'removeToken' => '/web/token/del',
        'getToastNotifications' => '/web/notifications/feed',
        'clearShoutBox' => '/web/shoutbox/clear',
        'shoutboxPost' => '/web/shoutbox/post',
        'shoutboxEdit' => '/web/shoutbox/edit',
        'shoutboxDelete' => '/web/shoutbox/delete',
        'shoutboxReact' => '/web/shoutbox/react',
        'getPasskeyCreateArgs' => '/web/passkey/create-args',
        'processPasskeyCreate' => '/web/passkey/create',
        'getPasskeyList' => '/web/passkey/list',
        'deletePasskey' => '/web/passkey/delete',
        'getPasskeyGetArgs' => '/web/passkey/get-args',
        'processPasskeyGet' => '/web/passkey/get',
    ];

    public static function uriFor(string $action): ?string
    {
        return self::MAP[$action] ?? null;
    }
}
