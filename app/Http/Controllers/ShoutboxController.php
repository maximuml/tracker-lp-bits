<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\ShoutboxRepository;
use App\Support\Permissions;
use App\Support\Shoutbox;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Nexus\Database\NexusLock;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShoutboxController extends LegacyController
{
    private ShoutboxRepository $repository;

    public function __construct(ShoutboxRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        return $this->success($this->repository->history($request));
    }

    public function shoutbox(Request $request): Response
    {
        $currentUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);

        $del = (int) $request->input('del', 0);
        if ($del > 0 && Validators::isId($del) && Permissions::userCan(PermissionEnum::SB_MANAGE->value, false, $currentUserId)) {
            DB::table('shoutbox')->where('id', $del)->delete();
            DB::table('shoutbox_reactions')->where('shoutbox_id', $del)->delete();
        }

        if ($request->input('sent') === 'yes' && $request->filled('shbox_text') && $currentUserId > 0) {
            $text = trim((string) $request->input('shbox_text'));
            if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
                return response('Message too long', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
            }

            $lock = new NexusLock("shoutbox:{$currentUserId}", 60);
            if (! $lock->acquire()) {
                return response('speaking too often', 429, ['Content-Type' => 'text/plain; charset=utf-8']);
            }

            DB::table('shoutbox')->insert([
                'userid' => $currentUserId,
                'date' => time(),
                'text' => $text,
                'type' => 'sb',
            ]);
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

        $langShoutbox = (array) (SupportContext::getGlobal('lang_shoutbox') ?? []);
        $isStaff = Permissions::userCan(PermissionEnum::SB_MANAGE->value, false, $currentUserId);

        $content = view('shoutbox.index', [
            'CURUSER' => $currentUser,
            'lang_shoutbox' => $langShoutbox,
            'isAjax' => $isAjax,
            'where' => $where,
            'refresh' => $refresh,
            'lastId' => $lastId,
            'rows' => $rows,
            'currentUserId' => $currentUserId,
            'isStaff' => $isStaff,
            'reactionData' => $reactionData,
        ])->render();

        return response($content, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function shoutboxHistory(Request $request): View|RedirectResponse
    {
        $result = $this->repository->history($request);

        $currentUser = SupportContext::getUser() ?? [];
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

        return $this->legacyPage($request, 'shoutbox_history', true, [
            'rows' => $rows,
            'total' => (int) ($result['total'] ?? 0),
            'page' => (int) ($result['page'] ?? 1),
            'perPage' => (int) ($result['per_page'] ?? 50),
            'filters' => (array) ($result['filters'] ?? []),
            'currentUserId' => $currentUserId,
            'isStaff' => Permission::can(PermissionEnum::SB_MANAGE),
            'csrfToken' => Shoutbox::csrfToken($currentUserId),
            'reactionData' => Shoutbox::prefetchReactions(array_values($shoutIds), $currentUserId),
            'userDisplayMap' => $userDisplayMap,
        ]);
    }

    public function shoutboxSse(Request $request): SymfonyResponse
    {
        $user = SupportContext::getUser();
        if ($user === null) {
            return new SymfonyResponse('', 403);
        }

        $type = (string) $request->input('type', 'shoutbox');
        $lastId = (int) ($request->header('Last-Event-ID') ?: $request->input('last_id', 0));
        $userId = (int) ($user['id'] ?? 0);

        $maxLoops = 30;
        $ttl = $maxLoops * 2 + 10;
        $maxStreams = 30;
        $globalKey = 'shoutbox_sse_global';

        $callback = function () use ($type, $lastId, $userId, $maxLoops, $ttl, $maxStreams, $globalKey) {
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
                http_response_code(503);

                return;
            }

            $userLock = new NexusLock('shoutbox_sse:'.$userId, $ttl);
            if (! $userLock->acquire()) {
                try {
                    $redis->decr($globalKey);
                } catch (\Throwable $e) {
                }
                http_response_code(429);

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

            $buildQuery = function (string $type, int $lastId) {
                $query = DB::table('shoutbox')
                    ->orderBy('id')
                    ->where('id', '>', $lastId);
                Shoutbox::applyTypeFilter($query, $type, SupportContext::getUser());

                return $query;
            };

            @ini_set('zlib.output_compression', 'Off');
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_implicit_flush(true);
            set_time_limit(0);
            ignore_user_abort(true);

            $query = $buildQuery($type, $lastId);

            for ($i = 0; $i < $maxLoops; $i++) {
                if (connection_aborted()) {
                    break;
                }

                $rows = $query->get();
                if (! $rows->isEmpty()) {
                    $maxId = (int) $rows->last()->id;
                    echo 'id: '.$maxId."\n";
                    echo "event: refresh\n";
                    echo 'data: '.json_encode(['count' => $rows->count()])."\n\n";
                    $this->flushSseOutput();
                    $lastId = $maxId;
                    $query = $buildQuery($type, $lastId);
                }

                echo "event: ping\n";
                echo "data: {}\n\n";
                $this->flushSseOutput();

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

    private function flushSseOutput(): void
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
