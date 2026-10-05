<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Requests\Ajax\ConsumeBenefitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class BenefitAjaxController extends AjaxController
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function consume(ConsumeBenefitRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->users->consumeBenefit((int) Auth::id(), $request->validated()));
    }
}
