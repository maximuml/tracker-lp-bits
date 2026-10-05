<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Http\Requests\Ajax\OfferShowRequest;
use Illuminate\Http\JsonResponse;

final class OfferAjaxController extends AjaxController
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
    ) {}

    public function show(OfferShowRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->offers->findOrFailById($request->integer('id'))->toArray());
    }
}
