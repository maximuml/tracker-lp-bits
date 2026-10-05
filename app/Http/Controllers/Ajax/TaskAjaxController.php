<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Http\Requests\Ajax\ClaimTaskRequest;
use App\Repositories\ExamUserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class TaskAjaxController extends AjaxController
{
    public function __construct(
        private readonly ExamUserRepository $examUsers,
    ) {}

    public function claim(ClaimTaskRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->examUsers->assignToUser(
            (int) Auth::id(),
            $request->integer('exam_id'),
        ));
    }
}
