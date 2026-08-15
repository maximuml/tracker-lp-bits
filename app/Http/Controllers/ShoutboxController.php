<?php

namespace App\Http\Controllers;

use App\Repositories\ShoutboxRepository;
use App\Support\Shoutbox;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;
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

    public function shoutbox(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'shoutbox', false);
    }

    public function shoutboxHistory(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'shoutbox_history', true);
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
            $redis = NexusDB::redis();

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

            $userLock = new \Nexus\Database\NexusLock('shoutbox_sse:' . $userId, $ttl);
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
                $query = NexusDB::table('shoutbox')
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
                    echo "id: " . $maxId . "\n";
                    echo "event: refresh\n";
                    echo "data: " . json_encode(['count' => $rows->count()]) . "\n\n";
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
