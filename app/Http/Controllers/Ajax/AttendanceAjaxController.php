<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Http\Requests\Ajax\AttendanceRetroactiveRequest;
use App\Repositories\AttendanceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class AttendanceAjaxController extends AjaxController
{
    public function __construct(
        private readonly AttendanceRepository $attendance,
    ) {}

    public function retroactive(AttendanceRetroactiveRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->attendance->retroactive(
            (int) Auth::id(),
            $request->string('date')->toString(),
        ));
    }
}
