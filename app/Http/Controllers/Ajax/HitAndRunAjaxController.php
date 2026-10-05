<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Http\Requests\Ajax\RemoveHitAndRunRequest;
use App\Repositories\BonusRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class HitAndRunAjaxController extends AjaxController
{
    public function __construct(
        private readonly BonusRepository $bonus,
    ) {}

    public function remove(RemoveHitAndRunRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->bonus->consumeToCancelHitAndRun(
            (int) Auth::id(),
            $request->integer('id'),
        ));
    }
}
