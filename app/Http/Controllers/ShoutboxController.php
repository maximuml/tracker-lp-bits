<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserFontsize;
use App\Enums\UserTheme;
use App\Repositories\ShoutboxRepository;
use App\Services\ShoutboxService;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\LegacyHeaderBag;
use App\Support\LegacyYesNo;
use App\Support\Lock;
use App\Support\NotificationFeed;
use App\Support\Shoutbox;
use App\Support\SseWriter;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShoutboxController extends LegacyController
{
    public function __construct(
        private readonly ShoutboxRepository $repository,
        private readonly ShoutboxService $shoutboxService,
        private readonly ActorContext $actorContext,
        private readonly CurrentUser $currentUser,
        private readonly LegacyHeaderBag $legacyHeaderBag,
        private readonly NotificationFeed $notificationFeed,
        private readonly SseWriter $sseWriter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        return $this->success($this->repository->history($request));
    }

    public function shoutbox(Request $request): Response
    {
        $actor = $this->actorContext;
        $currentUser = $actor->toLegacyArray();
        $currentUserId = $actor->id;

        $del = (int) $request->input('del', 0);
        if ($del > 0 && Validators::isId($del) && $actor->can(PermissionEnum::SB_MANAGE)) {
            $this->shoutboxService->deleteMessage($actor, $del);
        }

        if ($request->input('sent') === 'yes' && $request->filled('shbox_text') && $currentUserId > 0) {
            $text = trim((string) $request->input('shbox_text'));
            if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
                return response('Message too long', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
            }

            if (! $this->shoutboxService->postMessage($actor, $text)) {
                return response('speaking too often', 429, ['Content-Type' => 'text/plain; charset=utf-8']);
            }
        }

        $isAjax = ! empty($request->input('ajax'));
        $where = 'shoutbox';
        $refresh = (int) ($currentUser['sbrefresh'] ?? 120);
        $limit = (int) ($currentUser['sbnum'] ?? 70);

        $lastIdQuery = DB::table('shoutbox');
        Shoutbox::applyTypeFilter($lastIdQuery, $where, $currentUser ?: null);
        $lastId = (int) $lastIdQuery->max('id');

        $query = DB::table('shoutbox')->orderByDesc('date')->limit($limit);
        Shoutbox::applyTypeFilter($query, $where, $currentUser ?: null);
        $rows = $query->get();

        $shoutIds = array_values($rows->pluck('id')->map(fn ($id) => (int) $id)->all());
        $reactionData = Shoutbox::prefetchReactions($shoutIds, $currentUserId);

        $userIds = array_filter(array_unique($rows->pluck('userid')->map(fn ($id) => (int) $id)->all()));
        foreach ($userIds as $userId) {
            if ($userId > 0) {
                UserDisplay::row($userId);
            }
        }

        $isStaff = $actor->can(PermissionEnum::SB_MANAGE);
        $items = $this->decorateShoutRows($rows, $currentUser, $currentUserId, $isStaff, $reactionData);

        $content = view('shoutbox.index', [
            'isAjax' => $isAjax,
            'where' => $where,
            'refresh' => $refresh,
            'lastId' => $lastId,
            'items' => $items,
            'shoutCsrf' => Shoutbox::csrfToken($currentUserId),
            'theme' => UserTheme::fromStringSafe(is_string($currentUser['theme'] ?? null) ? $currentUser['theme'] : null)->value,
            'fontSize' => UserFontsize::fromMixed($currentUser['fontsize'] ?? null)->stringValue(),
        ])->render();

        return response($content, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param  iterable<int, mixed>  $rows
     * @param  array<string, mixed>  $currentUser
     * @param  array<string, mixed>  $reactionData
     * @return list<array<string, mixed>>
     */
    private function decorateShoutRows(iterable $rows, array $currentUser, int $currentUserId, bool $isStaff, array $reactionData): array
    {
        $reactionCounts = (array) ($reactionData['counts'] ?? []);
        $reactionMine = (array) ($reactionData['mine'] ?? []);
        $reactionUsers = (array) ($reactionData['users'] ?? []);
        $showAvatars = LegacyYesNo::isYes($currentUser['avatars'] ?? null);
        $tooltipAvatar = (string) (__('legacy/shoutbox.tooltip_avatar'));
        $tooltipReply = (string) (__('legacy/shoutbox.tooltip_nick_reply'));
        $labelMore = (string) (__('legacy/shoutbox.shout_show_more'));
        $labelLess = (string) (__('legacy/shoutbox.shout_show_less'));
        $groupWindowSec = 120;

        $items = [];
        $prevUserId = 0;
        $prevDate = 0;
        foreach ($rows as $row) {
            $arr = (array) $row;
            $currUserId = (int) ($arr['userid'] ?? 0);
            $currDate = (int) ($arr['date'] ?? 0);
            $shoutId = (int) ($arr['id'] ?? 0);
            $isContinuation = $currUserId > 0
                && $currUserId === $prevUserId
                && $prevDate > 0
                && abs($prevDate - $currDate) <= $groupWindowSec;

            $editedTime = '';
            if (! empty($arr['edited_at']) && (int) $arr['edited_at'] > 0) {
                $editedTime = SafeHtml::fromTrustedHtml(Shoutbox::formatTime((int) $arr['edited_at'], true));
            }

            $avatarUrl = 'pic/default_avatar.png';
            $nickReplyName = '';
            if ($currUserId > 0) {
                $username = UserDisplay::username($currUserId, false, true, true, true, false, false, '', true);
                $userRow = UserDisplay::row($currUserId);
                $userRow = is_array($userRow) ? $userRow : [];
                $nickReplyName = trim((string) ($userRow['username'] ?? ''));
                $classBadge = Shoutbox::classBadge((int) ($userRow['class'] ?? 0));
                if ($showAvatars) {
                    $rawAvatar = trim((string) ($userRow['avatar'] ?? ''));
                    if ($rawAvatar !== '') {
                        $avatarUrl = $rawAvatar;
                    }
                }
                if ($nickReplyName !== '' && $currentUserId > 0) {
                    $username = (string) preg_replace(
                        '#href="[^"]*userdetails\.php\?id=\d+"#',
                        'href="#" class="shout-nick-reply" data-nick="'.htmlspecialchars($nickReplyName, ENT_QUOTES).'" title="'.htmlspecialchars($tooltipReply, ENT_QUOTES).'"',
                        (string) $username,
                        1
                    );
                }
            } else {
                $username = (string) (__('legacy/shoutbox.text_guest'));
                $classBadge = '';
            }

            $mentionsMe = false;
            $message = Shoutbox::formatMessage((string) ($arr['text'] ?? ''), $currentUserId, $mentionsMe);
            $isLong = mb_strlen(strip_tags((string) $message)) > 280;

            $rowClasses = ['shoutrow'];
            if ($mentionsMe) {
                $rowClasses[] = 'shoutrow-mentions-me';
            }
            if ($isContinuation) {
                $rowClasses[] = 'shout-row-grouped';
                $username = '';
                $classBadge = '';
            }

            $items[] = [
                'rowClass' => implode(' ', $rowClasses),
                'time' => SafeHtml::fromTrustedHtml(Shoutbox::formatTime($currDate, true)),
                'actions' => SafeHtml::fromTrustedHtml(Shoutbox::renderActions($arr, $currentUserId, $isStaff)),
                'avatarUrl' => $avatarUrl,
                'avatarUserId' => $currUserId,
                'avatarTooltip' => $tooltipAvatar,
                'avatarSpacer' => $isContinuation,
                'classBadge' => SafeHtml::fromTrustedHtml($classBadge),
                'username' => SafeHtml::fromTrustedHtml($username),
                'reactions' => SafeHtml::fromTrustedHtml(Shoutbox::renderReactions(
                    $shoutId,
                    $currentUserId,
                    is_array($reactionCounts[$shoutId] ?? null) ? $reactionCounts[$shoutId] : [],
                    is_array($reactionMine[$shoutId] ?? null) ? array_values($reactionMine[$shoutId]) : [],
                    is_array($reactionUsers[$shoutId] ?? null) ? $reactionUsers[$shoutId] : []
                )),
                'msgId' => $shoutId,
                'msgLong' => $isLong,
                'msgRaw' => (string) ($arr['text'] ?? ''),
                'msgFormatted' => SafeHtml::fromTrustedHtml($message),
                'editedTime' => $editedTime,
                'labelMore' => $labelMore,
                'labelLess' => $labelLess,
            ];

            $prevUserId = $currUserId;
            $prevDate = $currDate;
        }

        return $items;
    }

    public function shoutboxHistory(Request $request): View|RedirectResponse
    {
        $result = $this->repository->history($request);

        $currentUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $rows = (array) ($result['data'] ?? []);
        $shoutIds = array_map(fn ($r) => (int) ($r['id'] ?? 0), $rows);
        $userIds = array_filter(array_unique(array_map(fn ($r) => (int) ($r['userid'] ?? 0), $rows)));

        $userDisplayMap = [];
        foreach ($userIds as $uid) {
            if ($uid > 0) {
                $userDisplayMap[$uid] = UserDisplay::username($uid, false, true, true, true, false, false, '', true);
            }
        }

        $isStaff = Permission::can(PermissionEnum::SB_MANAGE);
        $reactionData = Shoutbox::prefetchReactions(array_values($shoutIds), $currentUserId);
        $filters = (array) ($result['filters'] ?? []);
        $perPage = (int) ($result['per_page'] ?? 50);
        $total = (int) ($result['total'] ?? 0);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;
        $paginationBase = $totalPages > 1
            ? 'shoutbox_history.php?'.http_build_query(array_filter($filters, fn ($v) => $v !== '')).'&page='
            : '';

        return $this->legacyPage($request, 'shoutbox_history', true, [
            'items' => $this->decorateHistoryRows($rows, $currentUserId, $isStaff, $reactionData, $userDisplayMap),
            'page' => (int) ($result['page'] ?? 1),
            'totalPages' => $totalPages,
            'paginationBase' => $paginationBase,
            'filters' => $filters,
            'csrfToken' => Shoutbox::csrfToken($currentUserId),
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  array<string, mixed>  $reactionData
     * @param  array<int, SafeHtml>  $userDisplayMap
     * @return list<array<string, mixed>>
     */
    private function decorateHistoryRows(array $rows, int $currentUserId, bool $isStaff, array $reactionData, array $userDisplayMap): array
    {
        $reactionCounts = (array) ($reactionData['counts'] ?? []);
        $reactionMine = (array) ($reactionData['mine'] ?? []);
        $reactionUsers = (array) ($reactionData['users'] ?? []);

        $items = [];
        foreach ($rows as $arr) {
            if (! is_array($arr)) {
                continue;
            }
            $shoutId = (int) ($arr['id'] ?? 0);
            $uid = (int) ($arr['userid'] ?? 0);
            $username = $uid > 0
                ? (string) ($userDisplayMap[$uid] ?? '')
                : (string) (__('legacy/shoutbox.text_guest'));
            $mentionsMe = false;
            $message = Shoutbox::formatMessage((string) ($arr['text'] ?? ''), $currentUserId, $mentionsMe);
            $editedTime = '';
            if (! empty($arr['edited_at']) && (int) $arr['edited_at'] > 0) {
                $editedTime = SafeHtml::fromTrustedHtml(Shoutbox::formatTime((int) $arr['edited_at'], true));
            }
            $items[] = [
                'time' => SafeHtml::fromTrustedHtml(Shoutbox::formatTime((int) ($arr['date'] ?? 0), true)),
                'actions' => SafeHtml::fromTrustedHtml(Shoutbox::renderActions($arr, $currentUserId, $isStaff)),
                'username' => SafeHtml::fromTrustedHtml($username),
                'reactions' => SafeHtml::fromTrustedHtml(Shoutbox::renderReactions(
                    $shoutId,
                    $currentUserId,
                    is_array($reactionCounts[$shoutId] ?? null) ? $reactionCounts[$shoutId] : [],
                    is_array($reactionMine[$shoutId] ?? null) ? array_values($reactionMine[$shoutId]) : [],
                    is_array($reactionUsers[$shoutId] ?? null) ? $reactionUsers[$shoutId] : []
                )),
                'mentionsMe' => $mentionsMe,
                'msgId' => $shoutId,
                'msgLong' => false,
                'msgRaw' => (string) ($arr['text'] ?? ''),
                'msgFormatted' => SafeHtml::fromTrustedHtml($message),
                'editedTime' => $editedTime,
            ];
        }

        return $items;
    }

    public function shoutboxSse(Request $request): SymfonyResponse
    {
        $user = $this->currentUser->get();
        if ($user === null) {
            return new SymfonyResponse('', 403);
        }

        $type = (string) $request->input('type', 'shoutbox');
        $lastId = (int) ($request->header('Last-Event-ID') ?: $request->input('last_id', 0));
        $feedCursors = [
            'pm' => (int) $request->input('last_pm_id', 0),
            'shout' => (int) $request->input('last_shout_id', 0),
            'comment' => (int) $request->input('last_comment_id', 0),
            'topic_reply' => (int) $request->input('last_reply_id', 0),
        ];
        $userId = (int) ($user['id'] ?? 0);

        $maxLoops = 30;
        $ttl = $maxLoops * 2 + 10;
        $maxStreams = 30;
        $globalKey = 'shoutbox_sse_global';
        $isNotifications = $type === 'notifications';

        $callback = function () use ($type, $lastId, $feedCursors, $userId, $maxLoops, $ttl, $maxStreams, $globalKey, $isNotifications) {
            $redis = Redis::connection()->client();

            $active = (int) $redis->incr($globalKey);
            if ($active === 1) {
                $redis->expire($globalKey, $ttl + 60);
            }
            if ($active > $maxStreams) {
                try {
                    $redis->decr($globalKey);
                } catch (\Throwable $e) {
                }
                // T-11: Use LegacyHeaderBag instead of SAPI http_response_code()
                // to avoid cross-request status leakage under Octane.
                $this->legacyHeaderBag->setStatusCode(503);

                return;
            }

            $userLock = new Lock('sse:'.$type.':'.$userId, $ttl);
            if (! $userLock->acquire()) {
                try {
                    $redis->decr($globalKey);
                } catch (\Throwable $e) {
                }
                $this->legacyHeaderBag->setStatusCode(429);

                return;
            }

            register_shutdown_function(function () use ($redis, $globalKey, $userLock) {
                try {
                    $userLock->release();
                } catch (\Throwable $e) {
                }
                try {
                    $redis->decr($globalKey);
                } catch (\Throwable $e) {
                }
            });

            @ini_set('zlib.output_compression', 'Off');
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_implicit_flush(true);
            set_time_limit(0);
            ignore_user_abort(true);

            if ($isNotifications) {
                $this->runNotificationLoop($userId, $feedCursors, $maxLoops);

                return;
            }

            $buildQuery = function (string $type, int $lastId) {
                $query = DB::table('shoutbox')
                    ->orderBy('id')
                    ->where('id', '>', $lastId);
                Shoutbox::applyTypeFilter($query, $type, $this->currentUser->get());

                return $query;
            };

            $query = $buildQuery($type, $lastId);

            for ($i = 0; $i < $maxLoops; $i++) {
                if (connection_aborted()) {
                    break;
                }

                $rows = $query->get();
                if (! $rows->isEmpty()) {
                    $maxId = (int) $rows->last()->id;
                    $this->sseWriter->event('refresh', (string) json_encode(['count' => $rows->count()]), $maxId);
                    $lastId = $maxId;
                    $query = $buildQuery($type, $lastId);
                }

                $this->sseWriter->ping();

                sleep(2);
            }
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, int>  $cursors
     */
    private function runNotificationLoop(int $userId, array $cursors, int $maxLoops): void
    {
        for ($i = 0; $i < $maxLoops; $i++) {
            if (connection_aborted()) {
                break;
            }

            $data = $this->notificationFeed->since($userId, $cursors);
            $cursors = $data['cursors'];

            if ($data['notifications'] !== []) {
                $this->sseWriter->event('notifications', (string) json_encode($data), $cursors['pm']);
            } else {
                $this->sseWriter->ping();
            }

            sleep(2);
        }
    }
}
