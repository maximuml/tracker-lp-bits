<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Http\Requests\Ajax\RemoveLeechWarnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class ModerationAjaxController extends AjaxController
{
    public function __construct(
        private readonly UserModerationRepositoryInterface $userModeration,
    ) {}

    public function removeLeechWarn(RemoveLeechWarnRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->userModeration->removeLeechWarn(
            (int) Auth::id(),
            $request->integer('uid'),
        ));
    }
}
