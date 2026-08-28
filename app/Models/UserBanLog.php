<?php

declare(strict_types=1);

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

use App\Support\LegacyDb;
use App\Support\Logger;

class UserBanLog extends NexusModel
{
    /** @var string */
    protected $table = 'user_ban_logs';

    /** @var list<string> */
    protected $fillable = ['uid', 'username', 'operator', 'reason'];

    /** @var bool */
    public $timestamps = true;

    /** @return  mixed */
    public static function clearUserBanLogDuplicate()
    {
        $lists = UserBanLog::query()
            ->selectRaw('min(id) as id, uid, count(*) as counts')
            ->groupBy('uid')
            ->having('counts', '>', 1)
            ->get();
        if ($lists->isEmpty()) {
            Logger::writeWithContext((string) ('sql: '.LegacyDb::lastQuery(false, 'json').', no data to delete'), (string) 'info', (bool) false);

            return;
        }
        $idArr = $lists->pluck('id')->toArray();
        $uidArr = $lists->pluck('uid')->toArray();
        $result = UserBanLog::query()->whereIn('uid', $uidArr)->whereNotIn('id', $idArr)->delete();
        Logger::writeWithContext((string) ('sql: '.LegacyDb::lastQuery(false, 'json').", result: {$result}"), (string) 'info', (bool) false);
    }
}
