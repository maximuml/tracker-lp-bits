<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\Offer;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\BonusRepository;
use App\Repositories\TorrentModerationRepository;
use App\Services\Ajax\MedalActions;
use App\Services\Ajax\PasskeyActions;
use App\Services\Ajax\ShoutboxActions;
use App\Support\CurrentUser;
use App\Support\ToastNotifications;

final class AjaxService
{
    /**
     * Explicit whitelist of actions that may be dispatched via the /ajax endpoint.
     *
     * The controller checks `in_array($action, self::ALLOWED_ACTIONS)` before
     * calling dispatch(). Handler groups expose their action names through
     * their own ACTIONS constants; every remaining entry is a private method
     * on this class, so no public method besides dispatch() can ever become
     * an AJAX endpoint.
     *
     * @var array<int, string>
     */
    public const ALLOWED_ACTIONS = [
        'attendanceRetroactive',
        'removeUserLeechWarn',
        'getOffer',
        'approvalModal',
        'approval',
        'removeHitAndRun',
        'consumeBenefit',
        ...ShoutboxActions::ACTIONS,
        ...MedalActions::ACTIONS,
        'claimTask',
        'addToken',
        'removeToken',
        ...PasskeyActions::ACTIONS,
        'getToastNotifications',
    ];

    /** @var array<int, string> */
    private const MISC_ACTIONS = [
        'attendanceRetroactive',
        'removeUserLeechWarn',
        'getOffer',
        'approvalModal',
        'approval',
        'removeHitAndRun',
        'consumeBenefit',
        'claimTask',
        'addToken',
        'removeToken',
        'getToastNotifications',
    ];

    public function __construct(
        private readonly AttendanceRepository $attendanceRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserModerationRepositoryInterface $userModerationRepository,
        private readonly TorrentModerationRepository $torrentModerationRepository,
        private readonly BonusRepository $bonusRepository,
        private readonly ExamRepositoryInterface $examRepository,
        private readonly CurrentUser $currentUser,
        private readonly ShoutboxActions $shoutboxActions,
        private readonly PasskeyActions $passkeyActions,
        private readonly MedalActions $medalActions,
    ) {}

    /** @param array<string, mixed> $params */
    public function dispatch(string $action, array $params): mixed
    {
        return match (true) {
            in_array($action, ShoutboxActions::ACTIONS, true) => $this->shoutboxActions->{$action}($params),
            in_array($action, PasskeyActions::ACTIONS, true) => $this->passkeyActions->{$action}($params),
            in_array($action, MedalActions::ACTIONS, true) => $this->medalActions->{$action}($params),
            in_array($action, self::MISC_ACTIONS, true) => $this->{$action}($params),
            default => throw new \InvalidArgumentException("Unknown ajax action: {$action}"),
        };
    }

    /** @param array<string, mixed> $params */
    private function attendanceRetroactive(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->attendanceRepository;

        return $rep->retroactive($CURUSER['id'], $params['date']);
    }

    /** @param array<string, mixed> $params */
    private function removeUserLeechWarn(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->userModerationRepository;

        return $rep->removeLeechWarn($CURUSER['id'], $params['uid']);
    }

    /** @param array<string, mixed> $params */
    private function getOffer(array $params): mixed
    {
        $offer = Offer::query()->findOrFail($params['id']);

        return $offer->toArray();
    }

    /** @param array<string, mixed> $params */
    private function approvalModal(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->torrentModerationRepository;

        return $rep->buildApprovalModal($CURUSER['id'], (int) $params['torrent_id']);
    }

    /** @param array<string, mixed> $params */
    private function approval(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        foreach (['torrent_id', 'approval_status'] as $field) {
            if (! (isset($params[$field]))) {
                throw new \InvalidArgumentException("Require $field");
            }
        }
        $rep = $this->torrentModerationRepository;

        return $rep->approval($CURUSER['id'], $params);
    }

    /** @param array<string, mixed> $params */
    private function removeHitAndRun(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->bonusRepository;

        return $rep->consumeToCancelHitAndRun($CURUSER['id'], $params['id']);
    }

    /** @param array<string, mixed> $params */
    private function consumeBenefit(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->userRepository;

        return $rep->consumeBenefit($CURUSER['id'], $params);
    }

    /** @param array<string, mixed> $params */
    private function claimTask(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->examRepository;

        return $rep->assignToUser($CURUSER['id'], $params['exam_id']);
    }

    /** @param array<string, mixed> $params */
    private function addToken(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        if (empty($params['name'])) {
            throw new \InvalidArgumentException('Name is required');
        }
        $userId = (int) ($CURUSER['id'] ?? 0);
        $user = User::query()->findOrFail($userId, User::$commonFields);
        $user->createToken($params['name']);

        return true;
    }

    /** @param array<string, mixed> $params */
    private function removeToken(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        if (empty($params['id'])) {
            throw new \InvalidArgumentException('id is required');
        }
        $userId = (int) ($CURUSER['id'] ?? 0);
        $user = User::query()->findOrFail($userId, User::$commonFields);
        $user->tokens()->where('id', $params['id'])->delete();

        return true;
    }

    /** @param array<string, mixed> $params */
    private function getToastNotifications(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $lastPmId = (int) ($params['last_pm_id'] ?? 0);
        $lastShoutId = (int) ($params['last_shout_id'] ?? 0);
        $init = ! empty($params['init']);

        return ToastNotifications::get((int) $CURUSER['id'], $lastPmId, $lastShoutId, $init);
    }
}
