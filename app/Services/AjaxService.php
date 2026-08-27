<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Offer;
use App\Models\SeedBoxRecord;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\BonusRepository;
use App\Repositories\ExamRepository;
use App\Repositories\MedalRepository;
use App\Repositories\SeedBoxRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserPasskeyRepository;
use App\Repositories\UserRepository;
use App\Support\CurrentUser;
use App\Support\Shoutbox;
use App\Support\ToastNotifications;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusLock;

final class AjaxService
{
    /**
     * Explicit whitelist of actions that may be dispatched via the /ajax endpoint.
     *
     * The controller checks `in_array($action, self::ALLOWED_ACTIONS)` before
     * calling the method. This prevents any new public method on this class
     * from being accidentally exposed as an AJAX endpoint without an explicit
     * registration here.
     *
     * @var array<int, string>
     */
    public const ALLOWED_ACTIONS = [
        'toggleUserMedalStatus',
        'attendanceRetroactive',
        'removeUserLeechWarn',
        'getOffer',
        'approvalModal',
        'approval',
        'addSeedBoxRecord',
        'removeSeedBoxRecord',
        'removeHitAndRun',
        'consumeBenefit',
        'clearShoutBox',
        'shoutboxEdit',
        'shoutboxDelete',
        'shoutboxReact',
        'buyMedal',
        'giftMedal',
        'saveUserMedal',
        'claimTask',
        'addToken',
        'removeToken',
        'getPasskeyCreateArgs',
        'processPasskeyCreate',
        'deletePasskey',
        'getPasskeyList',
        'getPasskeyGetArgs',
        'processPasskeyGet',
        'getToastNotifications',
    ];

    public function __construct(
        private readonly MedalRepository $medalRepository,
        private readonly AttendanceRepository $attendanceRepository,
        private readonly UserRepository $userRepository,
        private readonly TorrentRepository $torrentRepository,
        private readonly SeedBoxRepository $seedBoxRepository,
        private readonly BonusRepository $bonusRepository,
        private readonly ExamRepository $examRepository,
        private readonly UserPasskeyRepository $userPasskeyRepository,
    ) {}

    /** @param array<string, mixed> $params */
    public function toggleUserMedalStatus(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->medalRepository;

        return $rep->toggleUserMedalStatus($params['id'], $CURUSER['id']);
    }

    /** @param array<string, mixed> $params */
    public function attendanceRetroactive(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->attendanceRepository;

        return $rep->retroactive($CURUSER['id'], $params['date']);
    }

    /** @param array<string, mixed> $params */
    public function removeUserLeechWarn(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->userRepository;

        return $rep->removeLeechWarn($CURUSER['id'], $params['uid']);
    }

    /** @param array<string, mixed> $params */
    public function getOffer(array $params): mixed
    {
        $offer = Offer::query()->findOrFail($params['id']);

        return $offer->toArray();
    }

    /** @param array<string, mixed> $params */
    public function approvalModal(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->torrentRepository;

        return $rep->buildApprovalModal($CURUSER['id'], (int) $params['torrent_id']);
    }

    /** @param array<string, mixed> $params */
    public function approval(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        foreach (['torrent_id', 'approval_status'] as $field) {
            if (! (isset($params[$field]))) {
                throw new \InvalidArgumentException("Require $field");
            }
        }
        $rep = $this->torrentRepository;

        return $rep->approval($CURUSER['id'], $params);
    }

    /** @param array<string, mixed> $params */
    public function addSeedBoxRecord(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->seedBoxRepository;
        $params['uid'] = $CURUSER['id'];
        $params['type'] = SeedBoxRecord::TYPE_USER;
        $params['status'] = SeedBoxRecord::STATUS_UNAUDITED;

        return $rep->store($params);
    }

    /** @param array<string, mixed> $params */
    public function removeSeedBoxRecord(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->seedBoxRepository;

        return $rep->delete($params['id'], $CURUSER['id']);
    }

    /** @param array<string, mixed> $params */
    public function removeHitAndRun(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->bonusRepository;

        return $rep->consumeToCancelHitAndRun($CURUSER['id'], $params['id']);
    }

    /** @param array<string, mixed> $params */
    public function consumeBenefit(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->userRepository;

        return $rep->consumeBenefit($CURUSER['id'], $params);
    }

    /** @param array<string, mixed> $params */
    public function clearShoutBox(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $user = User::query()->find($CURUSER['id'] ?? 0);
        if (! $user instanceof User || ! Permission::can(PermissionEnum::SB_MANAGE, $user)) {
            throw new \RuntimeException('No permission');
        }
        DB::table('shoutbox')->delete();
        DB::table('shoutbox_reactions')->delete();

        return true;
    }

    /** @param array<string, mixed> $params */
    public function shoutboxEdit(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $id = (int) ($params['id'] ?? 0);
        $text = trim((string) ($params['text'] ?? ''));
        if ($id <= 0 || $text === '') {
            throw new \InvalidArgumentException('Invalid input');
        }
        if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException('Message too long');
        }
        $msg = DB::table('shoutbox')->where('id', $id)->first();
        if (! $msg) {
            throw new \RuntimeException('Message not found');
        }
        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);
        if ($msgUserId !== (int) $CURUSER['id'] && ! Permission::can(PermissionEnum::SB_MANAGE)) {
            throw new \RuntimeException('No permission');
        }
        if ((time() - $msgDate) > Shoutbox::EDIT_WINDOW && ! Permission::can(PermissionEnum::SB_MANAGE)) {
            throw new \RuntimeException('Edit window expired');
        }
        $editLock = new NexusLock('shoutbox_edit:'.$CURUSER['id'], 10);
        if (! $editLock->acquire()) {
            throw new \RuntimeException('Editing too often');
        }
        try {
            DB::table('shoutbox')->where('id', $id)->update([
                'text' => $text,
                'edited_by' => $CURUSER['id'],
                'edited_at' => time(),
            ]);

            return true;
        } finally {
            $editLock->release();
        }
    }

    /** @param array<string, mixed> $params */
    public function shoutboxDelete(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid input');
        }
        $msg = DB::table('shoutbox')->where('id', $id)->first();
        if (! $msg) {
            return true;
        }
        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);
        if ($msgUserId !== (int) $CURUSER['id'] && ! Permission::can(PermissionEnum::SB_MANAGE)) {
            throw new \RuntimeException('No permission');
        }
        if ((time() - $msgDate) > Shoutbox::EDIT_WINDOW && ! Permission::can(PermissionEnum::SB_MANAGE)) {
            throw new \RuntimeException('Delete window expired');
        }
        $deleteLock = new NexusLock('shoutbox_delete:'.$CURUSER['id'], 10);
        if (! $deleteLock->acquire()) {
            throw new \RuntimeException('Deleting too often');
        }
        try {
            DB::table('shoutbox')->where('id', $id)->delete();
            DB::table('shoutbox_reactions')->where('shoutbox_id', $id)->delete();

            return true;
        } finally {
            $deleteLock->release();
        }
    }

    /** @param array<string, mixed> $params */
    public function shoutboxReact(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $id = (int) ($params['id'] ?? 0);
        $reaction = (string) ($params['reaction'] ?? '');
        if ($id <= 0 || ! in_array($reaction, Shoutbox::REACTIONS, true)) {
            throw new \InvalidArgumentException('Invalid reaction');
        }
        $reactLock = new NexusLock('shoutbox_react:'.$CURUSER['id'], 5);
        if (! $reactLock->acquire()) {
            throw new \RuntimeException('Reacting too often');
        }
        try {
            $table = DB::table('shoutbox_reactions');
            $existing = $table->where('shoutbox_id', $id)->where('user_id', $CURUSER['id'])->where('reaction', $reaction)->first();
            if ($existing) {
                $table->where('id', $existing->id)->delete();

                return 'removed';
            }
            $table->insert([
                'shoutbox_id' => $id,
                'user_id' => $CURUSER['id'],
                'reaction' => $reaction,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return 'added';
        } finally {
            $reactLock->release();
        }
    }

    /** @param array<string, mixed> $params */
    public function buyMedal(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->bonusRepository;

        return $rep->consumeToBuyMedal($CURUSER['id'], $params['medal_id']);
    }

    /** @param array<string, mixed> $params */
    public function giftMedal(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->bonusRepository;

        return $rep->consumeToGiftMedal($CURUSER['id'], $params['medal_id'], $params['uid']);
    }

    /** @param array<string, mixed> $params */
    public function saveUserMedal(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $data = [];
        foreach ($params as $param) {
            if (! is_array($param) || ! isset($param['name'], $param['value'])) {
                continue;
            }
            $fieldAndId = explode('_', $param['name']);
            if (count($fieldAndId) < 2) {
                continue;
            }
            $field = $fieldAndId[0];
            $id = $fieldAndId[1];
            $value = $param['value'];
            $data[$id][$field] = $value;
        }
        $rep = $this->medalRepository;

        return $rep->saveUserMedal($CURUSER['id'], $data);
    }

    /** @param array<string, mixed> $params */
    public function claimTask(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->examRepository;

        return $rep->assignToUser($CURUSER['id'], $params['exam_id']);
    }

    /** @param array<string, mixed> $params */
    public function addToken(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        if (empty($params['name'])) {
            throw new \InvalidArgumentException('Name is required');
        }
        $userId = (int) ($CURUSER['id'] ?? 0);
        $user = User::query()->findOrFail($userId, User::$commonFields);
        $user->createToken($params['name']);

        return true;
    }

    /** @param array<string, mixed> $params */
    public function removeToken(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        if (empty($params['id'])) {
            throw new \InvalidArgumentException('id is required');
        }
        $userId = (int) ($CURUSER['id'] ?? 0);
        $user = User::query()->findOrFail($userId, User::$commonFields);
        $user->tokens()->where('id', $params['id'])->delete();

        return true;
    }

    /** @param array<string, mixed> $params */
    public function getPasskeyCreateArgs(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->getCreateArgs($CURUSER['id'], $CURUSER['username']);
    }

    /** @param array<string, mixed> $params */
    public function processPasskeyCreate(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->processCreate($CURUSER['id'], $params['challengeId'], $params['clientDataJSON'], $params['attestationObject']);
    }

    /** @param array<string, mixed> $params */
    public function deletePasskey(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->delete($CURUSER['id'], $params['credentialId']);
    }

    /** @param array<string, mixed> $params */
    public function getPasskeyList(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->getList($CURUSER['id']);
    }

    /** @param array<string, mixed> $params */
    public function getPasskeyGetArgs(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->getGetArgs();
    }

    /** @param array<string, mixed> $params */
    public function processPasskeyGet(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $rep = $this->userPasskeyRepository;

        return $rep->processGet($params['challengeId'], $params['id'], $params['clientDataJSON'], $params['authenticatorData'], $params['signature'], $params['userHandle']);
    }

    /** @param array<string, mixed> $params */
    public function getToastNotifications(array $params): mixed
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $lastPmId = (int) ($params['last_pm_id'] ?? 0);
        $lastShoutId = (int) ($params['last_shout_id'] ?? 0);
        $init = ! empty($params['init']);

        return ToastNotifications::get((int) $CURUSER['id'], $lastPmId, $lastShoutId, $init);
    }
}
