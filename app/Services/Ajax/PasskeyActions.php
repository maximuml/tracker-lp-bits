<?php

declare(strict_types=1);

namespace App\Services\Ajax;

use App\Repositories\UserPasskeyRepository;
use App\Support\CurrentUser;

final class PasskeyActions
{
    /** @var array<int, string> */
    public const ACTIONS = [
        'getPasskeyCreateArgs',
        'processPasskeyCreate',
        'deletePasskey',
        'getPasskeyList',
        'getPasskeyGetArgs',
        'processPasskeyGet',
    ];

    public function __construct(
        private readonly UserPasskeyRepository $userPasskeyRepository,
        private readonly CurrentUser $currentUser,
    ) {}

    /** @param array<string, mixed> $params */
    public function getPasskeyCreateArgs(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->getCreateArgs($CURUSER['id'], $CURUSER['username']);
    }

    /** @param array<string, mixed> $params */
    public function processPasskeyCreate(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->processCreate($CURUSER['id'], $params['challengeId'], $params['clientDataJSON'], $params['attestationObject']);
    }

    /** @param array<string, mixed> $params */
    public function deletePasskey(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->delete($CURUSER['id'], $params['credentialId']);
    }

    /** @param array<string, mixed> $params */
    public function getPasskeyList(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->getList($CURUSER['id']);
    }

    /** @param array<string, mixed> $params */
    public function getPasskeyGetArgs(array $params): mixed
    {
        $rep = $this->userPasskeyRepository;

        return $rep->getGetArgs();
    }

    /** @param array<string, mixed> $params */
    public function processPasskeyGet(array $params): mixed
    {
        $rep = $this->userPasskeyRepository;

        return $rep->processGet($params['challengeId'], $params['id'], $params['clientDataJSON'], $params['authenticatorData'], $params['signature'], $params['userHandle']);
    }
}
