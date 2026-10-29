<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Http\Requests\Ajax\RemoveHitAndRunRequest;
use App\Services\BonusPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class HitAndRunAjaxController extends AjaxController
{
    public function __construct(
        private readonly BonusPurchaseService $bonus,
    ) {}

    public function remove(RemoveHitAndRunRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->bonus->consumeToCancelHitAndRun(
            (int) Auth::id(),
            $request->integer('id'),
        ));
    }
}
