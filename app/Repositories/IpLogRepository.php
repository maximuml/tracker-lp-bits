<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\IpLog;
use App\Support\Environment;
use App\Support\Input;
use App\Support\Logger;
use App\Support\Network;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class IpLogRepository extends BaseRepository
{
    const CACHE_KEY_PREFIX = 'nexus_ip_logs';

    private const CACHE_TIME = 72 * 3600;

    /**
     * @param  mixed  $userId
     * @param  mixed  $uri
     * @param  mixed  $ipArr
     */
    public static function saveToCache($userId, $uri = null, $ipArr = null): void
    {
        if (! is_numeric($userId) || $userId <= 0) {
            Logger::writeWithContext((string) "invalid userId: {$userId}", (string) 'error', (bool) false);

            return;
        }
        if (Environment::isTesting()) {
            return;
        }
        $redis = Redis::connection()->client();
        if ($uri === null) {
            $parsed_uri = parse_url(Input::serverValue('REQUEST_URI', ''));
            $uri = $parsed_uri['path'] ?? '/';
        }
        if ($ipArr === null) {
            $ipArr = [Network::clientIp()];
        }
        $key = sprintf('%s:%s', self::CACHE_KEY_PREFIX, date('Y-m-d-H'));
        foreach ($ipArr as $ip) {
            $field = sprintf('%s|%s|%s', $userId, $ip, $uri);
            $result = $redis->hincrby($key, $field, 1);
            Logger::writeWithContext((string) "success hincrby {$key} {$field}, result: {$result}", (string) 'debug', (bool) false);
            if ($result === 1) {
                $redis->expire($key, self::CACHE_TIME);
            }
        }
    }

    public static function saveToDB(): void
    {
        $beginTimestamp = microtime(true);
        $redis = Redis::connection()->client();
        $begin = Carbon::now()->subSeconds(self::CACHE_TIME);
        $end = Carbon::now()->subHours(1);
        $interval = \DateInterval::createFromDateString('1 hour');
        $period = new \DatePeriod($begin->clone(), $interval, $end);
        $size = 2000;
        Logger::writeWithContext((string) sprintf('begin: %s, end: %s, size: %s', $begin->toDateTimeString(), $end->toDateTimeString(), $size), (string) 'info', (bool) false);
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        foreach ($period as $dt) {
            $key = sprintf('%s:%s', self::CACHE_KEY_PREFIX, $dt->format('Y-m-d-H'));
            if (! $redis->exists($key)) {
                Logger::writeWithContext((string) "key: {$key} not found", (string) 'debug', (bool) false);

                continue;
            }
            if ($redis->hlen($key) == 0) {
                Logger::writeWithContext((string) "key: {$key} length = 0", (string) 'debug', (bool) false);
                $redis->unlink($key);
            }
            Logger::writeWithContext((string) "handing key: {$key}", (string) 'info', (bool) false);
            // 遍历hash
            $it = null;
            while ($arr_keys = $redis->hScan($key, $it, '*', $size)) {
                $insert = [];
                foreach ($arr_keys as $field => $value) {
                    [$userId, $ip, $uri] = explode('|', $field);
                    $insert[] = [
                        'userid' => $userId,
                        'ip' => $ip,
                        'uri' => $uri,
                        'access' => date('Y-m-d H:i:s'),
                        'count' => intval($value),
                    ];
                }
                if (! empty($insert)) {
                    IpLog::query()->insert($insert);
                }
                Logger::writeWithContext((string) ("key: {$key}, it: {$it}, count: ".count($insert)), (string) 'info', (bool) false);
            }
            $redis->unlink($key);
            Logger::writeWithContext((string) "handle key: {$key} done!", (string) 'info', (bool) false);
        }
        Logger::writeWithContext((string) sprintf('all done! cost time: %.3f sec.', microtime(true) - $beginTimestamp), (string) 'info', (bool) false);
    }
}
