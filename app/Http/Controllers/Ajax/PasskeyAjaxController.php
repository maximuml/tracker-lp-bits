<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\DTOs\Auth\ActorContext;
use App\Http\Requests\Ajax\PasskeyCreateArgsRequest;
use App\Http\Requests\Ajax\PasskeyCreateRequest;
use App\Http\Requests\Ajax\PasskeyDeleteRequest;
use App\Http\Requests\Ajax\PasskeyGetArgsRequest;
use App\Http\Requests\Ajax\PasskeyGetRequest;
use App\Http\Requests\Ajax\PasskeyListRequest;
use App\Repositories\UserPasskeyRepository;
use Illuminate\Http\JsonResponse;

final class PasskeyAjaxController extends AjaxController
{
    public function __construct(
        private readonly UserPasskeyRepository $userPasskeyRepository,
        private readonly ActorContext $actor,
    ) {}

    public function createArgs(PasskeyCreateArgsRequest $request): JsonResponse
    {
        return $this->respond($request, function () {
            $user = $this->actor->toLegacyArray();

            return $this->userPasskeyRepository->getCreateArgs($this->actor->id, $this->actor->username);
        });
    }

    public function processCreate(PasskeyCreateRequest $request): JsonResponse
    {
        return $this->respond($request, function () use ($request) {
            $user = $this->actor->toLegacyArray();

            return $this->userPasskeyRepository->processCreate(
                $this->actor->id,
                $request->input('challengeId'),
                $request->input('clientDataJSON'),
                $request->input('attestationObject'),
            );
        });
    }

    public function delete(PasskeyDeleteRequest $request): JsonResponse
    {
        return $this->respond($request, function () use ($request) {
            $user = $this->actor->toLegacyArray();

            return $this->userPasskeyRepository->delete($this->actor->id, $request->input('credentialId'));
        });
    }

    public function list(PasskeyListRequest $request): JsonResponse
    {
        return $this->respond($request, function () {
            $user = $this->actor->toLegacyArray();

            return $this->userPasskeyRepository->getList($this->actor->id);
        });
    }

    /**
     * Guest-facing: called by the login page before the user is
     * authenticated. Registered outside the auth.nexus group.
     */
    public function getArgs(PasskeyGetArgsRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->userPasskeyRepository->getGetArgs());
    }

    /**
     * Guest-facing: verifies the assertion and sets the login cookie
     * inside UserPasskeyRepository::processGet (AuthCookie::setLoginCookie).
     */
    public function processGet(PasskeyGetRequest $request): JsonResponse
    {
        return $this->respond($request, function () use ($request) {
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
