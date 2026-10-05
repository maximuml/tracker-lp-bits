<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Http\Requests\Ajax\ToastFeedRequest;
use App\Support\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class NotificationAjaxController extends AjaxController
{
    public function __construct(
        private readonly NotificationFeed $notificationFeed,
    ) {}

    public function feed(ToastFeedRequest $request): JsonResponse
    {
        $cursors = [
            'pm' => $request->integer('last_pm_id'),
            'shout' => $request->integer('last_shout_id'),
            'comment' => $request->integer('last_comment_id'),
            'topic_reply' => $request->integer('last_reply_id'),
            'staff' => $request->integer('last_staff_id'),
        ];

        return $this->respond(fn () => $this->notificationFeed->since(
            (int) Auth::id(),
            $cursors,
            $request->boolean('init'),
        ));
    }
}
