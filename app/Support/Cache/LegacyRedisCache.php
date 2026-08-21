<?php

namespace App\Support\Cache;

use App\Support\Config;
use App\Support\Environment;
use App\Support\Logger;

class LegacyRedisCache
{
    public bool $isEnabled = false;

    public int $clearCache = 0;

    public string $language = 'en';

    /** @var array<int|string, mixed> */
    public array $Page = [];

    public int $Row = 1;

    public int $Part = 0;

    public string $MemKey = '';

    public int $Duration = 0;

    public int $cacheReadTimes = 0;

    public int $cacheWriteTimes = 0;

    /** @var array<string, array<string, int>> */
    public array $keyHits = [];

    /** @var array<int, string> */
    public array $languageFolderArray = [];

    public ?\Redis $redis = null;

    public function __construct()
    {
        $connectResult = $this->connect(); // Connect to Redis
        if ($connectResult) {
            $this->isEnabled = true;
        } else {
            $this->isEnabled = false;
        }
    }

    private function connect(): bool
    {
        $config = Config::get('nexus.redis', null);
        $redis = new \Redis;
        $params = [
            $config['host'],
        ];
        if (! empty($config['port'])) {
            $params[] = $config['port'];
        }
        if (isset($config['timeout']) && is_numeric($config['timeout'])) {
            $params[] = $config['timeout'];
        }
        if (Environment::isFpm()) {
            try {
                $connectResult = $redis->pconnect(...$params);
            } catch (\Exception $e) {
                Logger::writeWithContext((string) "redis pconnect failed: {$e->getMessage()}, retry one time", (string) 'error', (bool) false);
                $redis->close();
                $redis = new \Redis;
                $connectResult = $redis->pconnect(...$params);
            }
            Logger::writeWithContext((string) "redis pconnect: {$connectResult}", (string) 'debug', (bool) false);
        } else {
            try {
                $connectResult = $redis->connect(...$params);
            } catch (\Exception $e) {
                $connectResult = false;
            }
            \App\Support\Logger::writeWithContext((string) "redis connect: {$connectResult}", (string) 'debug', (bool) false);
        }
        if (! empty($config['password'])) {
            $connectResult = $connectResult && $redis->auth($config['password']);
        }
        if ($connectResult) {
            $this->redis = $redis;
            if (is_numeric($config['database'])) {
                $redis->select((int) $config['database']);
            }
        } else {
            if (\App\Support\Environment::isTesting()) {
                $this->isEnabled = false;
                return false;
            }
            throw new \RuntimeException("Redis connect fail.");
        }

        return true;
    }

    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setClearCache(int $isEnabled): void
    {
        $this->clearCache = $isEnabled;
    }

    /** @return array<int, string> */
    public function getLanguageFolderArray(): array
    {
        return $this->languageFolderArray;
    }

    /** @param array<int, string> $languageFolderArray */
    public function setLanguageFolderArray(array $languageFolderArray): void
    {
        $this->languageFolderArray = $languageFolderArray;
    }

    public function getClearCache(): int
    {
        return $this->clearCache;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function new_page(string $MemKey = '', int $Duration = 3600, bool $Lang = true): void
    {
        if ($Lang) {
            $language = $this->getLanguage();
            $this->MemKey = $language.'_'.$MemKey;
        } else {
            $this->MemKey = $MemKey;
        }
        $this->Duration = $Duration;
        $this->Row = 1;
        $this->Part = 0;
        $this->Page = [];
    }

    public function set_key(): void {}

    // ---------- Adding functions ----------//

    public function add_row(): void
    {
        $this->Part = 0;
        $this->Page[$this->Row] = [];
    }

    public function end_row(): void
    {
        $this->Row++;
    }

    public function add_part(): void
    {
        ob_start();
    }

    public function end_part(): void
    {
        $this->Page[$this->Row][$this->Part] = ob_get_clean();
        $this->Part++;
    }

    // Shorthand for:
    // add_row();
    // add_part();
    // You should only use this function if the row is only going to have one part in it (convention),
    // although it will theoretically work with multiple parts.
    public function add_whole_row(): void
    {
        $this->Part = 0;
        $this->Page[$this->Row] = [];
        ob_start();
    }

    // Shorthand for:
    // end_part();
    // end_row();
    // You should only use this function if the row is only going to have one part in it (convention),
    // although it will theoretically work with multiple parts.
    public function end_whole_row(): void
    {
        $this->Page[$this->Row][$this->Part] = ob_get_clean();
        $this->Row++;
    }

    // Set a variable that will only be availabe when the system is on its row
    // This variable is stored in the same way as pages, so don't use an integer for the $Key.
    public function set_row_value(string|int $Key, mixed $Value): void
    {
        $this->Page[$this->Row][$Key] = $Value;
    }

    // Set a variable that will always be available, no matter what row the system is on.
    // This variable is stored in the same way as rows, so don't use an integer for the $Key.
    public function set_constant_value(string|int $Key, mixed $Value): void
    {
        $this->Page[$Key] = $Value;
    }

    // Inserts a 'false' value into a row, which breaks out of while loops.
    // This is not necessary if the end of $this->Page is also the end of the while loop.
    public function break_loop(): void
    {
        if (count($this->Page) > 0) {
            $this->Page[$this->Row] = false;
            $this->Row++;
        }
    }

    // ---------- Locking functions ----------//

    // These functions 'lock' a key.
    // Users cannot proceed until it is unlocked.

    public function lock(string $Key): void
    {
        $this->cache_value('lock_'.$Key, 'true', 3600);
    }

    public function unlock(string $Key): void
    {
        if ($this->redis === null) {
            return;
        }
        //        $this->delete('lock_'.$Key);
        $this->redis->del('lock_'.$Key);
    }

    // ---------- Caching functions ----------//

    // Cache $this->Page and resets $this->Row and $this->Part
    public function cache_page(): void
    {
        $this->cache_value($this->MemKey, $this->Page, $this->Duration);
        $this->Row = 0;
        $this->Part = 0;
    }

    // Exact same as cache_page, but does not store the page in cache
    // This is so that we can use classes that normally cache values in
    // situations where caching is not required
    public function setup_page(): void
    {
        $this->Row = 0;
        $this->Part = 0;
    }

    // Wrapper for Memcache::set, with the zlib option removed and default duration of 1 hour
    public function cache_value(string $Key, mixed $Value, int $Duration = 3600): void
    {
        if (! $this->getIsEnabled() || $this->redis === null) {
            return;
        }
        $Value = $this->serialize($Value);
        //        $this->set($Key,$Value, 0, $Duration);
        $this->redis->set($Key, $Value, $Duration);
        $this->cacheWriteTimes++;
        $this->keyHits['write'][$Key] = ! isset($this->keyHits['write'][$Key]) ? 1 : $this->keyHits['write'][$Key] + 1;
    }

    // ---------- Getting functions ----------//

    // Returns the next row in the page
    // If there's only one part in the row, return that part.
    public function next_row(): mixed
    {
        $this->Row++;
        $this->Part = 0;
        if (! isset($this->Page[$this->Row]) || $this->Page[$this->Row] == false) {
            return false;
        } elseif (count($this->Page[$this->Row]) == 1) {
            return $this->Page[$this->Row][0];
        } else {
            return $this->Page[$this->Row];
        }
    }

    // Returns the next part in the row
    public function next_part(): mixed
    {
        $Return = $this->Page[$this->Row][$this->Part];
        $this->Part++;

        return $Return;
    }

    // Returns a 'row value' (a variable that changes for each row - see above).
    public function get_row_value(string|int $Key): mixed
    {
        return $this->Page[$this->Row][$Key];
    }

    // Returns a 'constant value' (a variable that doesn't change with the rows - see above)
    public function get_constant_value(string|int $Key): mixed
    {
        return $this->Page[$Key];
    }

    // If a cached version of the page exists, set $this->Page to it and return true.
    // Otherwise, return false.
    public function get_page(): bool
    {
        $Result = $this->get_value($this->MemKey);
        if ($Result) {
            $this->Row = 0;
            $this->Part = 0;
            $this->Page = $Result;

            return true;
        } else {
            return false;
        }
    }

    // Wrapper for Memcache::get. Why? Because wrappers are cool.
    public function get_value(string $Key): mixed
    {
        if (! $this->getIsEnabled()) {
            return false;
        }
        if ($this->getClearCache()) {
            $this->delete_value($Key);

            return false;
        }
        // If we've locked it
        // Xia Zuojie: we disable the following lock feature 'cause we don't need it and it doubles the time to fetch a value from a key
        /*while($Lock = $this->get('lock_'.$Key)){
            sleep(2);
        }*/

        if ($this->redis === null) {
            return false;
        }
        $Return = $this->redis->get($Key);
        $Return = ! is_null($Return) ? $this->unserialize($Return) : null;
        $this->cacheReadTimes++;
        $this->keyHits['read'][$Key] = ! isset($this->keyHits['read'][$Key]) ? 1 : $this->keyHits['read'][$Key] + 1;

        return $Return;
    }

    // Wrapper for Memcache::delete. For a reason, see above.
    public function delete_value(string $Key, bool $AllLang = false): int
    {
        if (! $this->getIsEnabled() || $this->redis === null) {
            return 0;
        }
        $deleted = $this->redis->del($Key);
        if ($AllLang) {
            $langfolder_array = $this->getLanguageFolderArray();
            foreach ($langfolder_array as $lf) {
                $this->redis->del($lf.'_'.$Key);
            }
        }

        return (int) $deleted;
    }

    public function getCacheReadTimes(): int
    {
        return $this->cacheReadTimes;
    }

    public function getCacheWriteTimes(): int
    {
        return $this->cacheWriteTimes;
    }

    /** @return array<string, int> */
    public function getKeyHits(string $type = 'read'): array
    {
        return $this->keyHits[$type] ?? [];
    }

    /**
     * Serialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) && ! in_array($value, [INF, -INF]) && ! is_nan((float) $value) ? $value : serialize($value);
    }

    /**
     * Unserialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : unserialize($value);
    }

    /**
     * get the redis client
     *
     * @date 2021/1/15
     */
    public function getRedis(): ?\Redis
    {
        if ($this->getIsEnabled()) {
            return $this->redis;
        }

        return null;
    }
}
