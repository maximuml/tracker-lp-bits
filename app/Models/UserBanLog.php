<?php

/**
 * @property int $id
 * @property int $uid
 * @property string $username
 * @property int $operator
 * @property string|null $reason
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

class UserBanLog extends NexusModel
{
    /** @var  string */
    protected $table = 'user_ban_logs';

    /** @var  list<string> */
    protected $fillable = ['uid', 'username', 'operator', 'reason'];

    /** @var  bool */
    public $timestamps = true;

    /** @return  mixed */
    public static function clearUserBanLogDuplicate()
    {
        $lists = UserBanLog::query()
            ->selectRaw("min(id) as id, uid, count(*) as counts")
            ->groupBy('uid')
            ->having("counts", ">", 1)
            ->get();
        if ($lists->isEmpty()) {
            do_log("sql: " . last_query() . ", no data to delete");
            return;
        }
        $idArr = $lists->pluck("id")->toArray();
        $uidArr = $lists->pluck('uid')->toArray();
        $result = UserBanLog::query()->whereIn("uid", $uidArr)->whereNotIn("id", $idArr)->delete();
        do_log("sql: " . last_query() . ", result: $result");
    }


}
