<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Ajax\AjaxFeatureServices;
use App\Services\Ajax\AjaxTorrentRepositories;
use App\Services\Ajax\AjaxUserRepositories;
use App\Services\Ajax\PasskeyActions;
use App\Services\Ajax\ShoutboxActions;
use App\Support\CurrentUser;

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
        private readonly CurrentUser $currentUser,
        private readonly AjaxUserRepositories $users,
        private readonly AjaxTorrentRepositories $torrents,
        private readonly AjaxFeatureServices $features,
    ) {}

    /** @param array<string, mixed> $params */
    public function dispatch(string $action, array $params): mixed
    {
        return match (true) {
            in_array($action, ShoutboxActions::ACTIONS, true) => $this->features->shoutboxActions->{$action}($params),
            in_array($action, PasskeyActions::ACTIONS, true) => $this->features->passkeyActions->{$action}($params),
            in_array($action, self::MISC_ACTIONS, true) => $this->{$action}($params),
            default => throw new \InvalidArgumentException("Unknown ajax action: {$action}"),
        };
    }

    /** @param array<string, mixed> $params */
    private function attendanceRetroactive(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->users->attendance;

        return $rep->retroactive($CURUSER['id'], $params['date']);
    }

    /** @param array<string, mixed> $params */
    private function removeUserLeechWarn(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->users->userModeration;

        return $rep->removeLeechWarn($CURUSER['id'], $params['uid']);
    }

    /** @param array<string, mixed> $params */
    private function getOffer(array $params): mixed
    {
        $offer = $this->torrents->offers->findOrFailById((int) $params['id']);

        return $offer->toArray();
    }

    /** @param array<string, mixed> $params */
    private function approvalModal(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->torrents->torrentModeration;

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
        $rep = $this->torrents->torrentModeration;

        return $rep->approval($CURUSER['id'], $params);
    }

    /** @param array<string, mixed> $params */
    private function removeHitAndRun(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->torrents->bonus;

        return $rep->consumeToCancelHitAndRun($CURUSER['id'], $params['id']);
    }

    /** @param array<string, mixed> $params */
    private function consumeBenefit(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->users->users;

        return $rep->consumeBenefit($CURUSER['id'], $params);
    }

    /** @param array<string, mixed> $params */
    private function claimTask(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->users->examUsers;

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
        $user = $this->users->userAccount->findOrFailByIdFields($userId, User::$commonFields);
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
        $user = $this->users->userAccount->findOrFailByIdFields($userId, User::$commonFields);
        $user->tokens()->where('id', $params['id'])->delete();

        return true;
    }

    /** @param array<string, mixed> $params */
    private function getToastNotifications(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $cursors = [
            'pm' => (int) ($params['last_pm_id'] ?? 0),
            'shout' => (int) ($params['last_shout_id'] ?? 0),
            'comment' => (int) ($params['last_comment_id'] ?? 0),
            'topic_reply' => (int) ($params['last_reply_id'] ?? 0),
            'staff' => (int) ($params['last_staff_id'] ?? 0),
        ];
        $init = ! empty($params['init']);

        return $this->features->notificationFeed->since((int) $CURUSER['id'], $cursors, $init);
    }
}
