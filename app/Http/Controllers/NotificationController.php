<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Auth\ActorContext;
use App\Http\Requests\MarkReadRequest;
use App\Repositories\NotificationFeedRepository;
use App\Support\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BasePageController
{
    public function __construct(
        private readonly NotificationFeed $feed,
        private readonly ActorContext $actor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($this->actor->isGuest()) {
            return response()->json(['ret' => 403, 'msg' => 'Unauthorized'], 403);
        }

        $offset = max(0, (int) $request->query('offset', 0));

        return response()->json(['ret' => 0, 'data' => $this->feed->unread((int) $this->actor->id, $offset)]);
    }

    public function markReadSubmit(MarkReadRequest $request): JsonResponse
    {
        if ($this->actor->isGuest()) {
            return response()->json(['ret' => 403, 'msg' => 'Unauthorized'], 403);
        }

        // Optional snapshot watermark from the unread() response — events
        // that arrived after the panel was opened stay unread.
        $watermark = null;
        $raw = $request->input('watermark');
        if (is_array($raw)) {
            $watermark = [];
            foreach (NotificationFeedRepository::CHANNELS as $channel) {
                if (isset($raw[$channel]) && is_numeric($raw[$channel])) {
                    $watermark[$channel] = (int) $raw[$channel];
                }
            }
        }

        $cursors = $this->feed->markAllRead((int) $this->actor->id, $watermark);

        return response()->json(['ret' => 0, 'data' => ['cursors' => $cursors]]);
    }
}
