<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserFontsize;
use App\Enums\UserTheme;
use App\Services\ShoutboxService;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\NotificationFeed;
use App\Support\Shoutbox;
use App\Support\SseEventId;
use App\Support\SseWriter;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShoutboxController extends LegacyController
{
    public function __construct(
        private readonly ShoutboxRepositoryInterface $repository,
        private readonly ShoutboxService $shoutboxService,
        private readonly ActorContext $actorContext,
        private readonly CurrentUser $currentUser,
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

        $lastId = $this->repository->maxId($where, $currentUser ?: null);
        $rows = $this->repository->listLatest($where, $currentUser ?: null, $limit);

        $shoutIds = array_values($rows->pluck('id')->map(fn ($id) => (int) $id)->all());
        $reactionData = Shoutbox::prefetchReactions($shoutIds, $currentUserId);

        $userIds = array_filter(array_unique($rows->pluck('userid')->map(fn ($id) => (int) $id)->all()));
        UserDisplay::preload(array_values($userIds));

        $isStaff = $actor->can(PermissionEnum::SB_MANAGE);
        $items = Shoutbox::decorateRows($rows, $currentUser, $currentUserId, $isStaff, $reactionData);

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

    public function shoutboxHistory(Request $request): View|RedirectResponse
    {
        $result = $this->repository->history($request);

        $currentUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $rows = (array) ($result['data'] ?? []);
        $shoutIds = array_map(fn ($r) => (int) ($r['id'] ?? 0), $rows);
        $userIds = array_filter(array_unique(array_map(fn ($r) => (int) ($r['userid'] ?? 0), $rows)));

        UserDisplay::preload(array_values($userIds));
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
                'isGuest' => $uid <= 0,
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
        $isNotifications = $type === 'notifications';
        $lastId = (int) ($request->header('Last-Event-ID') ?: $request->input('last_id', 0));
        $feedCursors = SseEventId::applyTo(
            (string) ($request->header('Last-Event-ID') ?? ''),
            [
                'pm' => (int) $request->input('last_pm_id', 0),
                'shout' => (int) $request->input('last_shout_id', 0),
                'comment' => (int) $request->input('last_comment_id', 0),
                'topic_reply' => (int) $request->input('last_reply_id', 0),
                'staff' => (int) $request->input('last_staff_id', 0),
            ]
        );
        $userId = (int) ($user['id'] ?? 0);

        // Bounded stream lifetime: the client reconnects after the loop
        // ends and resumes via Last-Event-ID/cursor params.
        $maxLoops = max(1, min(30, (int) $request->input('loops', 30)));
        $interval = max(0, min(2, (int) $request->input('interval', 2)));
        $ttl = $maxLoops * ($interval + 1) + 10;
        $maxStreams = 30;
        $globalKey = 'shoutbox_sse_global';
        $lockKey = 'sse:'.$type.':'.$userId;

        // Admission control must run before StreamedResponse: once the
        // callback starts the status line is committed and a refusal
        // could no longer carry a real HTTP status.
        try {
            $redis = Redis::connection()->client();
            $active = (int) $redis->incr($globalKey);
            if ($active === 1) {
                $redis->expire($globalKey, $ttl + 60);
            }
            if ($active > $maxStreams) {
                try {
                    $redis->decr($globalKey);
                } catch (\Throwable) {
                }
                $this->sseCount('rejected', 'limit');

                return new SymfonyResponse('', 503);
            }

            // Latest-wins slot: a fresh stream from the same user
            // supersedes the previous one — otherwise a quick navigation
            // would keep getting rejected until the displaced loop notices
            // the client abort, which behind a proxy can take the whole
            // bounded lifetime. The displaced stream sees its token
            // overwritten, exits at the next iteration and its
            // owner-checked release no-ops on the stolen key.
            $streamToken = Str::random(32);
            $redis->setex($lockKey, $ttl, $streamToken);
        } catch (\Throwable) {
            // Fail closed: without Redis there is no counter/slot, so
            // allowing the stream would silently remove the bound.
            return new SymfonyResponse('', 503);
        }
        $this->sseCount('connects', $isNotifications ? 'notifications' : 'shoutbox');

        $owns = function () use ($redis, $lockKey, $streamToken): bool {
            try {
                return $redis->get($lockKey) === $streamToken;
            } catch (\Throwable) {
                // Transient Redis failure: keep streaming — the global
                // bound was enforced at admission and the loop is bounded.
                return true;
            }
        };

        $released = false;
        $release = function () use (&$released, $redis, $globalKey, $lockKey, $streamToken) {
            if ($released) {
                return;
            }
            $released = true;
            try {
                // Owner-checked delete: a displaced stream must not
                // remove the slot the newer stream now owns.
                $redis->eval(
                    'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("del", KEYS[1]) else return 0 end',
                    [$lockKey, $streamToken],
                    1
                );
            } catch (\Throwable) {
            }
            try {
                $redis->decr($globalKey);
            } catch (\Throwable) {
            }
        };
        // Safety net for hard fatals where finally{} never runs.
        register_shutdown_function($release);

        $callback = function () use ($type, $lastId, $feedCursors, $userId, $maxLoops, $interval, $isNotifications, $release, $owns) {
            @ini_set('zlib.output_compression', 'Off');
            if (PHP_SAPI !== 'cli') {
                // Drop pre-existing output buffers so frames flush in real
                // time under php-fpm. CLI SAPI covers both PHPUnit
                // (streamedContent() captures output in its own buffer —
                // clearing it would break the capture) and Octane workers.
                while (ob_get_level()) {
                    ob_end_clean();
                }
            }
            ob_implicit_flush(true);
            set_time_limit(0);
            ignore_user_abort(true);

            try {
                if ($isNotifications) {
                    $this->runNotificationLoop($userId, $feedCursors, $maxLoops, $interval, $owns);
                } else {
                    $this->runShoutLoop($type, $lastId, $maxLoops, $interval, $owns);
                }
            } finally {
                $release();
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
     * @param  callable(): bool  $owns
     */
    private function runShoutLoop(string $type, int $lastId, int $maxLoops, int $interval, callable $owns): void
    {
        $buildQuery = fn (string $type, int $lastId) => $this->repository->newAfterIdQuery($type, $lastId, $this->currentUser->get());
        $query = $buildQuery($type, $lastId);

        for ($i = 0; $i < $maxLoops; $i++) {
            if (connection_aborted() || ! $owns()) {
                break;
            }

            $rows = $query->get();
            if (! $rows->isEmpty()) {
                $maxId = (int) $rows->last()->id;
                $this->sseWriter->event('refresh', (string) json_encode(['count' => $rows->count()]), $maxId);
                $this->sseCount('events', 'shoutbox');
                $lastId = $maxId;
                $query = $buildQuery($type, $lastId);
            }

            $this->sseWriter->ping();

            sleep($interval);
        }
    }

    /**
     * @param  array<string, int>  $cursors
     * @param  callable(): bool  $owns
     */
    private function runNotificationLoop(int $userId, array $cursors, int $maxLoops, int $interval, callable $owns): void
    {
        for ($i = 0; $i < $maxLoops; $i++) {
            if (connection_aborted() || ! $owns()) {
                break;
            }

            // Idle ticks cost one primary-key probe; the full joined
            // fetch only runs when a source actually moved.
            if (! $this->notificationFeed->hasNewerThan($userId, $cursors)) {
                $this->sseWriter->ping();
                sleep($interval);

                continue;
            }

            $data = $this->notificationFeed->since($userId, $cursors);
            $cursors = $data['cursors'];

            if ($data['notifications'] !== []) {
                $this->sseWriter->event(
                    'notifications',
                    (string) json_encode($data),
                    SseEventId::encode($cursors)
                );
                $this->sseCount('events', 'notifications');
                $this->sseLag('notifications', $data['notifications']);
            } else {
                $this->sseWriter->ping();
            }

            sleep($interval);
        }
    }

    private function sseCount(string $name, string $label): void
    {
        try {
            Redis::connection()->client()->incr('metrics:sse_'.$name.':'.$label);
        } catch (\Throwable) {
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function sseLag(string $type, array $items): void
    {
        $newest = 0;
        foreach ($items as $item) {
            $newest = max($newest, (int) ($item['timestamp'] ?? 0));
        }
        if ($newest === 0) {
            return;
        }
        try {
            Redis::connection()->client()->set(
                'metrics:sse_lag_seconds:'.$type,
                (string) max(0, time() - $newest)
            );
        } catch (\Throwable) {
        }
    }
}
