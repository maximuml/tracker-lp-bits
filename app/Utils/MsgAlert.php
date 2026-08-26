<?php

namespace App\Utils;

use App\Support\Html;
use App\Support\UserDisplay;
use Illuminate\Support\Facades\Redis;

final class MsgAlert
{
    private static ?self $instance = null;

    /** @var array<string, array<string, mixed>> */
    private static array $alerts = [];

    private string $redisKeyPrefix = 'nexus_alerts';

    private function __construct()
    {
        $redis = Redis::connection()->client();
        $result = $redis->lRange($this->getListKey(), 0, 10);
        if (! empty($result)) {
            $nowTimestamp = time();
            $valid = [];
            foreach ($result as $item) {
                $arr = json_decode($item, true);
                if (is_array($arr) && $arr['deadline'] > $nowTimestamp) {
                    $valid[$arr['name']] = $arr;
                } else {
                    $redis->lRem($this->getListKey(), $item, 0);
                }
            }
            self::$alerts = $valid;
        }
    }

    private function __clone() {}

    public static function resetState(): void
    {
        self::$instance = null;
        self::$alerts = [];
    }

    public static function getInstance(): MsgAlert
    {
        if (isset(self::$instance)) {
            return self::$instance;
        }

        return self::$instance = new self;
    }

    public function add(string $name, int $deadline, string $text, string $url = '', string $color = 'red'): void
    {
        if (! isset(self::$alerts[$name])) {
            $params = compact('name', 'deadline', 'text', 'url', 'color');
            self::$alerts[$name] = $params;
            Redis::connection()->client()->rPush($this->getListKey(), json_encode($params));
        }
    }

    private function getListKey(): string
    {
        return sprintf('%s:%s', $this->redisKeyPrefix, UserDisplay::currentId());
    }

    public static function render(): void
    {
        $nowTimestamp = time();
        foreach (self::$alerts as $item) {
            if ($item['deadline'] > $nowTimestamp) {
                Html::messageAlertVoid($item['url'] ?: '', $item['text'], $item['color'] ?: 'red');
            }
        }
    }

    public function remove(string $name): void
    {
        foreach (self::$alerts as $item) {
            if ($item['name'] == $name) {
                unset(self::$alerts[$name]);
                Redis::connection()->client()->lRem($this->getListKey(), json_encode($item));
            }
        }
    }
}
