<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CurrentUser;
use App\Support\NotificationFeed;
use Illuminate\Http\JsonResponse;

class NotificationController extends LegacyController
{
    public function __construct(
        private readonly NotificationFeed $feed,
        private readonly CurrentUser $currentUser,
    ) {}

    public function index(): JsonResponse
    {
        $user = $this->currentUser->get();
        if ($user === null) {
            return response()->json(['ret' => 403, 'msg' => 'Unauthorized'], 403);
        }

        return response()->json(['ret' => 0, 'data' => $this->feed->unread((int) $user['id'])]);
    }

    public function markRead(): JsonResponse
    {
        $user = $this->currentUser->get();
        if ($user === null) {
            return response()->json(['ret' => 403, 'msg' => 'Unauthorized'], 403);
        }

        return response()->json(['ret' => 0, 'data' => ['counts' => $this->feed->markAllRead((int) $user['id'])]]);
    }
}
