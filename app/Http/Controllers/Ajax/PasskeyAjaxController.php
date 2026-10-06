<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Http\Requests\Ajax\PasskeyCreateArgsRequest;
use App\Http\Requests\Ajax\PasskeyCreateRequest;
use App\Http\Requests\Ajax\PasskeyDeleteRequest;
use App\Http\Requests\Ajax\PasskeyGetArgsRequest;
use App\Http\Requests\Ajax\PasskeyGetRequest;
use App\Http\Requests\Ajax\PasskeyListRequest;
use App\Repositories\UserPasskeyRepository;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PasskeyAjaxController extends AjaxController
{
    public function __construct(
        private readonly UserPasskeyRepository $userPasskeyRepository,
        private readonly CurrentUser $currentUser,
    ) {}

    public function createArgs(PasskeyCreateArgsRequest $request): JsonResponse
    {
        return $this->respond(function () {
            $user = $this->currentUser->get() ?? [];

            return $this->userPasskeyRepository->getCreateArgs($this->currentUser->id(), $this->currentUser->username());
        });
    }

    public function processCreate(PasskeyCreateRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $user = $this->currentUser->get() ?? [];

            return $this->userPasskeyRepository->processCreate(
                $this->currentUser->id(),
                $request->input('challengeId'),
                $request->input('clientDataJSON'),
                $request->input('attestationObject'),
            );
        });
    }

    public function delete(PasskeyDeleteRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $user = $this->currentUser->get() ?? [];

            return $this->userPasskeyRepository->delete($this->currentUser->id(), $request->input('credentialId'));
        });
    }

    public function list(PasskeyListRequest $request): JsonResponse
    {
        return $this->respond(function () {
            $user = $this->currentUser->get() ?? [];

            return $this->userPasskeyRepository->getList($this->currentUser->id());
        });
    }

    /**
     * Guest-facing: called by the login page before the user is
     * authenticated. Registered outside the auth.nexus group.
     */
    public function getArgs(PasskeyGetArgsRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->userPasskeyRepository->getGetArgs());
    }

    /**
     * Guest-facing: verifies the assertion and sets the login cookie
     * inside UserPasskeyRepository::processGet (AuthCookie::setLoginCookie).
     */
    public function processGet(PasskeyGetRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            return $this->userPasskeyRepository->processGet(
                $request->input('challengeId'),
                $request->input('id'),
                $request->input('clientDataJSON'),
                $request->input('authenticatorData'),
                $request->input('signature'),
                $request->input('userHandle'),
            );
        });
    }
}
