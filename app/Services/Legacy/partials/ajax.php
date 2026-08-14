<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!class_exists('AjaxInterface')) {

class AjaxInterface{

    public static function toggleUserMedalStatus($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\MedalRepository();
        return $rep->toggleUserMedalStatus($params['id'], $CURUSER['id']);
    }


    public static function attendanceRetroactive($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\AttendanceRepository();
        return $rep->retroactive($CURUSER['id'], $params['date']);
    }

    public static function removeUserLeechWarn($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserRepository();
        return $rep->removeLeechWarn($CURUSER['id'], $params['uid']);
    }

    public static function getOffer($params)
    {
        $offer = \App\Models\Offer::query()->findOrFail($params['id']);
        return $offer->toArray();
    }

    public static function approvalModal($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->buildApprovalModal($CURUSER['id'], $params['torrent_id']);
    }

    public static function approval($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        foreach (['torrent_id', 'approval_status',] as $field) {
            if (!(isset($params[$field]))) {
                throw new \InvalidArgumentException("Require $field");
            }
        }
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->approval($CURUSER['id'], $params);
    }

    public static function addSeedBoxRecord($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\SeedBoxRepository();
        $params['uid'] = $CURUSER['id'];
        $params['type'] = \App\Models\SeedBoxRecord::TYPE_USER;
        $params['status'] = \App\Models\SeedBoxRecord::STATUS_UNAUDITED;
        return $rep->store($params);
    }

    public static function removeSeedBoxRecord($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\SeedBoxRepository();
        return $rep->delete($params['id'], $CURUSER['id']);
    }

    public static function removeHitAndRun($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToCancelHitAndRun($CURUSER['id'], $params['id']);
    }

    public static function consumeBenefit($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserRepository();
        return $rep->consumeBenefit($CURUSER['id'], $params);
    }

    public static function clearShoutBox($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $user = \App\Models\User::query()->find($CURUSER['id'] ?? 0);
        if (! $user || ! \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::SB_MANAGE, $user)) {
            throw new \RuntimeException('No permission');
        }
        \Nexus\Database\NexusDB::table('shoutbox')->delete();
        \Nexus\Database\NexusDB::table('shoutbox_reactions')->delete();
        return true;
    }

    public static function shoutboxEdit($params)
    {
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

    public static function shoutboxDelete($params)
    {
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

    public static function shoutboxReact($params)
    {
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

    public static function buyMedal($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToBuyMedal($CURUSER['id'], $params['medal_id']);
    }

    public static function giftMedal($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToGiftMedal($CURUSER['id'], $params['medal_id'], $params['uid']);
    }

    public static function saveUserMedal($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        if (is_string($params)) {
            $params = json_decode($params, true);
        }
        if (!is_array($params)) {
            throw new \InvalidArgumentException('Invalid params');
        }
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

    public static function claimTask($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\ExamRepository();
        return $rep->assignToUser($CURUSER['id'], $params['exam_id']);
    }

    public static function addToken($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        if (empty($params['name'])) {
            throw new \InvalidArgumentException("Name is required");
        }
        $user = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
        $user->createToken($params['name']);
        return true;
    }

    public static function removeToken($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        if (empty($params['id'])) {
            throw new \InvalidArgumentException("id is required");
        }
        $user = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
        $user->tokens()->where('id', $params['id'])->delete();
        return true;
    }

    public static function getPasskeyCreateArgs($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getCreateArgs($CURUSER['id'], $CURUSER['username']);
    }

    public static function processPasskeyCreate($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->processCreate($CURUSER['id'], $params['challengeId'], $params['clientDataJSON'], $params['attestationObject']);
    }

    public static function deletePasskey($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->delete($CURUSER['id'], $params['credentialId']);
    }

    public static function getPasskeyList($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getList($CURUSER['id']);
    }

    public static function getPasskeyGetArgs($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getGetArgs();
    }

    public static function processPasskeyGet($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->processGet($params['challengeId'], $params['id'], $params['clientDataJSON'], $params['authenticatorData'], $params['signature'], $params['userHandle']);
    }

    public static function getToastNotifications($params)
    {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
        if (!is_array($params)) {
            $params = [];
        }
        $lastPmId = (int) ($params['last_pm_id'] ?? 0);
        $lastShoutId = (int) ($params['last_shout_id'] ?? 0);
        $init = !empty($params['init']);

        return \App\Support\ToastNotifications::get((int) $CURUSER['id'], $lastPmId, $lastShoutId, $init);
    }
}
}