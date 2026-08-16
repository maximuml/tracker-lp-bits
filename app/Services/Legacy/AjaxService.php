<?php

declare(strict_types=1);

namespace App\Services\Legacy;



final class AjaxService
{

    /** @param array<string, mixed> $params */
    public static function toggleUserMedalStatus(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\MedalRepository();
        return $rep->toggleUserMedalStatus($params['id'], $CURUSER['id']);
    }


    /** @param array<string, mixed> $params */
    public static function attendanceRetroactive(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\AttendanceRepository();
        return $rep->retroactive($CURUSER['id'], $params['date']);
    }

    /** @param array<string, mixed> $params */
    public static function removeUserLeechWarn(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserRepository();
        return $rep->removeLeechWarn($CURUSER['id'], $params['uid']);
    }

    /** @param array<string, mixed> $params */
    public static function getOffer(array $params): mixed {
        $offer = \App\Models\Offer::query()->findOrFail($params['id']);
        return $offer->toArray();
    }

    /** @param array<string, mixed> $params */
    public static function approvalModal(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->buildApprovalModal($CURUSER['id'], (int) $params['torrent_id']);
    }

    /** @param array<string, mixed> $params */
    public static function approval(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        foreach (['torrent_id', 'approval_status',] as $field) {
            if (!(isset($params[$field]))) {
                throw new \InvalidArgumentException("Require $field");
            }
        }
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->approval($CURUSER['id'], $params);
    }

    /** @param array<string, mixed> $params */
    public static function addSeedBoxRecord(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\SeedBoxRepository();
        $params['uid'] = $CURUSER['id'];
        $params['type'] = \App\Models\SeedBoxRecord::TYPE_USER;
        $params['status'] = \App\Models\SeedBoxRecord::STATUS_UNAUDITED;
        return $rep->store($params);
    }

    /** @param array<string, mixed> $params */
    public static function removeSeedBoxRecord(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\SeedBoxRepository();
        return $rep->delete($params['id'], $CURUSER['id']);
    }

    /** @param array<string, mixed> $params */
    public static function removeHitAndRun(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToCancelHitAndRun($CURUSER['id'], $params['id']);
    }

    /** @param array<string, mixed> $params */
    public static function consumeBenefit(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserRepository();
        return $rep->consumeBenefit($CURUSER['id'], $params);
    }

    /** @param array<string, mixed> $params */
    public static function clearShoutBox(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $user = \App\Models\User::query()->find($CURUSER['id'] ?? 0);
        if (! $user instanceof \App\Models\User || ! \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::SB_MANAGE, $user)) {
            throw new \RuntimeException('No permission');
        }
        \Nexus\Database\NexusDB::table('shoutbox')->delete();
        \Nexus\Database\NexusDB::table('shoutbox_reactions')->delete();
        return true;
    }

    /** @param array<string, mixed> $params */
    public static function shoutboxEdit(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $id = (int) ($params['id'] ?? 0);
        $text = trim((string) ($params['text'] ?? ''));
        if ($id <= 0 || $text === '') {
            throw new \InvalidArgumentException('Invalid input');
        }
        if (mb_strlen($text) > \App\Support\Shoutbox::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException('Message too long');
        }
        $msg = \Nexus\Database\NexusDB::table('shoutbox')->where('id', $id)->first();
        if (! $msg) {
            throw new \RuntimeException('Message not found');
        }
        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);
        if ($msgUserId !== (int) $CURUSER['id'] && ! \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::SB_MANAGE)) {
            throw new \RuntimeException('No permission');
        }
        if ((time() - $msgDate) > \App\Support\Shoutbox::EDIT_WINDOW && ! \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::SB_MANAGE)) {
            throw new \RuntimeException('Edit window expired');
        }
        $editLock = new \Nexus\Database\NexusLock('shoutbox_edit:' . $CURUSER['id'], 10);
        if (! $editLock->acquire()) {
            throw new \RuntimeException('Editing too often');
        }
        try {
            \Nexus\Database\NexusDB::table('shoutbox')->where('id', $id)->update([
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
    public static function shoutboxDelete(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid input');
        }
        $msg = \Nexus\Database\NexusDB::table('shoutbox')->where('id', $id)->first();
        if (! $msg) {
            return true;
        }
        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);
        if ($msgUserId !== (int) $CURUSER['id'] && ! \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::SB_MANAGE)) {
            throw new \RuntimeException('No permission');
        }
        if ((time() - $msgDate) > \App\Support\Shoutbox::EDIT_WINDOW && ! \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::SB_MANAGE)) {
            throw new \RuntimeException('Delete window expired');
        }
        $deleteLock = new \Nexus\Database\NexusLock('shoutbox_delete:' . $CURUSER['id'], 10);
        if (! $deleteLock->acquire()) {
            throw new \RuntimeException('Deleting too often');
        }
        try {
            \Nexus\Database\NexusDB::table('shoutbox')->where('id', $id)->delete();
            \Nexus\Database\NexusDB::table('shoutbox_reactions')->where('shoutbox_id', $id)->delete();
            return true;
        } finally {
            $deleteLock->release();
        }
    }

    /** @param array<string, mixed> $params */
    public static function shoutboxReact(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $id = (int) ($params['id'] ?? 0);
        $reaction = (string) ($params['reaction'] ?? '');
        if ($id <= 0 || ! in_array($reaction, \App\Support\Shoutbox::REACTIONS, true)) {
            throw new \InvalidArgumentException('Invalid reaction');
        }
        $reactLock = new \Nexus\Database\NexusLock('shoutbox_react:' . $CURUSER['id'], 5);
        if (! $reactLock->acquire()) {
            throw new \RuntimeException('Reacting too often');
        }
        try {
            $table = \Nexus\Database\NexusDB::table('shoutbox_reactions');
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
    public static function buyMedal(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToBuyMedal($CURUSER['id'], $params['medal_id']);
    }

    /** @param array<string, mixed> $params */
    public static function giftMedal(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToGiftMedal($CURUSER['id'], $params['medal_id'], $params['uid']);
    }

    /** @param array<string, mixed> $params */
    public static function saveUserMedal(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $data = [];
        foreach ($params as $param) {
            if (!is_array($param) || !isset($param['name'], $param['value'])) {
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
        $rep = new \App\Repositories\MedalRepository();
        return $rep->saveUserMedal($CURUSER['id'], $data);
    }

    /** @param array<string, mixed> $params */
    public static function claimTask(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\ExamRepository();
        return $rep->assignToUser($CURUSER['id'], $params['exam_id']);
    }

    /** @param array<string, mixed> $params */
    public static function addToken(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        if (empty($params['name'])) {
            throw new \InvalidArgumentException("Name is required");
        }
        $userId = (int) ($CURUSER['id'] ?? 0);
        $user = \App\Models\User::query()->findOrFail($userId, \App\Models\User::$commonFields);
        $user->createToken($params['name']);
        return true;
    }

    /** @param array<string, mixed> $params */
    public static function removeToken(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        if (empty($params['id'])) {
            throw new \InvalidArgumentException("id is required");
        }
        $userId = (int) ($CURUSER['id'] ?? 0);
        $user = \App\Models\User::query()->findOrFail($userId, \App\Models\User::$commonFields);
        $user->tokens()->where('id', $params['id'])->delete();
        return true;
    }

    /** @param array<string, mixed> $params */
    public static function getPasskeyCreateArgs(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getCreateArgs($CURUSER['id'], $CURUSER['username']);
    }

    /** @param array<string, mixed> $params */
    public static function processPasskeyCreate(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->processCreate($CURUSER['id'], $params['challengeId'], $params['clientDataJSON'], $params['attestationObject']);
    }

    /** @param array<string, mixed> $params */
    public static function deletePasskey(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->delete($CURUSER['id'], $params['credentialId']);
    }

    /** @param array<string, mixed> $params */
    public static function getPasskeyList(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getList($CURUSER['id']);
    }

    /** @param array<string, mixed> $params */
    public static function getPasskeyGetArgs(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getGetArgs();
    }

    /** @param array<string, mixed> $params */
    public static function processPasskeyGet(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->processGet($params['challengeId'], $params['id'], $params['clientDataJSON'], $params['authenticatorData'], $params['signature'], $params['userHandle']);
    }

    /** @param array<string, mixed> $params */
    public static function getToastNotifications(array $params): mixed {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $lastPmId = (int) ($params['last_pm_id'] ?? 0);
        $lastShoutId = (int) ($params['last_shout_id'] ?? 0);
        $init = !empty($params['init']);

        return \App\Support\ToastNotifications::get((int) $CURUSER['id'], $lastPmId, $lastShoutId, $init);
    }
}
