<?php
require "../include/bittorrent.php";
dbconn();

$action = $_POST['action'] ?? '';
$params = $_POST['params'] ?? [];

if ($action != 'getPasskeyGetArgs' && $action != 'processPasskeyGet') {
    loggedinorreturn();
}

class AjaxInterface{

    public static function toggleUserMedalStatus($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\MedalRepository();
        return $rep->toggleUserMedalStatus($params['id'], $CURUSER['id']);
    }


    public static function attendanceRetroactive($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\AttendanceRepository();
        return $rep->retroactive($CURUSER['id'], $params['date']);
    }

    public static function removeUserLeechWarn($params)
    {
        global $CURUSER;
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
        global $CURUSER;
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->buildApprovalModal($CURUSER['id'], $params['torrent_id']);
    }

    public static function approval($params)
    {
        global $CURUSER;
        foreach (['torrent_id', 'approval_status',] as $field) {
            if (!isset($params[$field])) {
                throw new \InvalidArgumentException("Require $field");
            }
        }
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->approval($CURUSER['id'], $params);
    }

    public static function addSeedBoxRecord($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\SeedBoxRepository();
        $params['uid'] = $CURUSER['id'];
        $params['type'] = \App\Models\SeedBoxRecord::TYPE_USER;
        $params['status'] = \App\Models\SeedBoxRecord::STATUS_UNAUDITED;
        return $rep->store($params);
    }

    public static function removeSeedBoxRecord($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\SeedBoxRepository();
        return $rep->delete($params['id'], $CURUSER['id']);
    }

    public static function removeHitAndRun($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToCancelHitAndRun($CURUSER['id'], $params['id']);
    }

    public static function consumeBenefit($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserRepository();
        return $rep->consumeBenefit($CURUSER['id'], $params);
    }

    public static function clearShoutBox($params)
    {
        global $CURUSER;
        user_can('sbmanage', true);
        \Nexus\Database\NexusDB::table('shoutbox')->delete();
        return true;
    }

    public static function shoutboxEdit($params)
    {
        global $CURUSER;
        $id = (int) ($params['id'] ?? 0);
        $text = trim((string) ($params['text'] ?? ''));
        if ($id <= 0 || $text === '') {
            throw new \InvalidArgumentException('Invalid input');
        }
        $msg = \Nexus\Database\NexusDB::table('shoutbox')->where('id', $id)->first();
        if (! $msg) {
            throw new \RuntimeException('Message not found');
        }
        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);
        if ($msgUserId !== (int) $CURUSER['id'] && ! user_can('sbmanage')) {
            throw new \RuntimeException('No permission');
        }
        if ((time() - $msgDate) > \App\Support\Shoutbox::EDIT_WINDOW && ! user_can('sbmanage')) {
            throw new \RuntimeException('Edit window expired');
        }
        \Nexus\Database\NexusDB::table('shoutbox')->where('id', $id)->update([
            'text' => $text,
            'edited_by' => $CURUSER['id'],
            'edited_at' => time(),
        ]);
        return true;
    }

    public static function shoutboxDelete($params)
    {
        global $CURUSER;
        $id = (int) ($params['id'] ?? 0);
        $msg = \Nexus\Database\NexusDB::table('shoutbox')->where('id', $id)->first();
        if (! $msg) {
            return true;
        }
        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);
        if ($msgUserId !== (int) $CURUSER['id'] && ! user_can('sbmanage')) {
            throw new \RuntimeException('No permission');
        }
        if ((time() - $msgDate) > \App\Support\Shoutbox::EDIT_WINDOW && ! user_can('sbmanage')) {
            throw new \RuntimeException('Delete window expired');
        }
        \Nexus\Database\NexusDB::table('shoutbox')->where('id', $id)->delete();
        \Nexus\Database\NexusDB::table('shoutbox_reactions')->where('shoutbox_id', $id)->delete();
        return true;
    }

    public static function shoutboxReact($params)
    {
        global $CURUSER;
        $id = (int) ($params['id'] ?? 0);
        $reaction = (string) ($params['reaction'] ?? '');
        if ($id <= 0 || ! in_array($reaction, \App\Support\Shoutbox::REACTIONS, true)) {
            throw new \InvalidArgumentException('Invalid reaction');
        }
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
    }

    public static function buyMedal($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToBuyMedal($CURUSER['id'], $params['medal_id']);
    }

    public static function giftMedal($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToGiftMedal($CURUSER['id'], $params['medal_id'], $params['uid']);
    }

    public static function saveUserMedal($params)
    {
        global $CURUSER;
        $data = [];
        foreach ($params as $param) {
            $fieldAndId = explode('_', $param['name']);
            $field = $fieldAndId[0];
            $id = $fieldAndId[1];
            $value = $param['value'];
            $data[$id][$field] = $value;
        }
    //    dd($params, $data);
        $rep = new \App\Repositories\MedalRepository();
        return $rep->saveUserMedal($CURUSER['id'], $data);
    }

    public static function claimTask($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\ExamRepository();
        return $rep->assignToUser($CURUSER['id'], $params['exam_id']);
    }

    public static function addToken($params)
    {
        global $CURUSER;
        if (empty($params['name'])) {
            throw new \InvalidArgumentException("Name is required");
        }
        $user = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
        $user->createToken($params['name']);
        return true;
    }

    public static function removeToken($params)
    {
        global $CURUSER;
        if (empty($params['id'])) {
            throw new \InvalidArgumentException("id is required");
        }
        $user = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
        $user->tokens()->where('id', $params['id'])->delete();
        return true;
    }

    public static function getPasskeyCreateArgs($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getCreateArgs($CURUSER['id'], $CURUSER['username']);
    }

    public static function processPasskeyCreate($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->processCreate($CURUSER['id'], $params['challengeId'], $params['clientDataJSON'], $params['attestationObject']);
    }

    public static function deletePasskey($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->delete($CURUSER['id'], $params['credentialId']);
    }

    public static function getPasskeyList($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getList($CURUSER['id']);
    }

    public static function getPasskeyGetArgs($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->getGetArgs();
    }

    public static function processPasskeyGet($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserPasskeyRepository();
        return $rep->processGet($params['challengeId'], $params['id'], $params['clientDataJSON'], $params['authenticatorData'], $params['signature'], $params['userHandle']);
    }
}

$class = 'AjaxInterface';
$reflection = new \ReflectionClass($class);

try {
    if($reflection->hasMethod($action) && $reflection->getMethod($action)->isStatic()) {
        $result = $class::$action($params);
        exit(json_encode(success($result)));
    } else {
        do_log("hacking attempt made by {$CURUSER['username']},uid {$CURUSER['id']}", 'error');
        throw new \RuntimeException("Invalid action: $action");
    }
}catch(\Throwable $exception){
    do_log($exception->getMessage() . $exception->getTraceAsString(), "error");
    exit(json_encode(fail($exception->getMessage(), $_POST)));
}
