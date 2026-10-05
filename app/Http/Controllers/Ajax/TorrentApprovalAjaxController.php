<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Http\Requests\Ajax\ApprovalModalRequest;
use App\Repositories\TorrentModerationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class TorrentApprovalAjaxController extends AjaxController
{
    public function __construct(
        private readonly TorrentModerationRepository $torrentModeration,
    ) {}

    public function modal(ApprovalModalRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->torrentModeration->buildApprovalModal(
            (int) Auth::id(),
            $request->integer('torrent_id'),
        ));
    }
}
