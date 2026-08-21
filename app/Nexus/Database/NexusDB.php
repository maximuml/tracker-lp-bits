<?php

namespace Nexus\Database;

use App\Models\OauthClient;
use App\Models\PersonalAccessToken;
use App\Support\Config;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\SupportContext;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;

class NexusDB
{
    private $driver;

    private static $instance;

    /**
     * @var Connection
     */
    private static $eloquentConnection;

    private $isConnected = false;

    private function __construct() {}

    private function __clone() {}

    const ELOQUENT_CONNECTION_NAME = 'mysql';

    public function setDriver(DBInterface $driver)
    {
        $this->driver = $driver;

        return $this;
    }

    public function getDriver()
    {
        return $this->driver;
    }

    public static function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }
        $instance = new self;
        //        $driver = new DBMysqli();
        $driver = new DBPdo;
        $instance->setDriver($driver);

        return self::$instance = $instance;
    }

    public function connect($host, $username, $password, $database, $port, $driver = 'mysql')
    {
        $result = $this->driver->connect($host, $username, $password, $database, $port, $driver);
        if (! $result) {
            throw new DatabaseException(sprintf('[%s]: %s', $this->errno(), $this->error()));
        }
        $this->isConnected = true;

        return true;
    }

    public function autoConnect()
    {
        if ($this->isConnected()) {
            return null;
        }
        $dbType = self::getConnectionName();
        $config = Config::get('nexus.database.connections.'.$dbType, null);

        return $this->connect($config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $dbType);
    }

    public function query(string $sql)
    {
        try {
            $this->autoConnect();

            return $this->driver->query($sql);
        } catch (\Exception $e) {
            Logger::writeWithContext((string) sprintf('%s [%s] %s', $e->getMessage(), $sql, $e->getTraceAsString()), (string) 'info', (bool) false);
            throw new DatabaseException($e->getMessage(), $sql);
        }

    }

    public function error()
    {
        return $this->driver->error();
    }

    public function errno()
    {
        return $this->driver->errno();
    }

    public function numRows($result)
    {
        return $this->driver->numRows($result);
    }

    public function select_db($database)
    {
        return $this->driver->selectDb($database);
    }

    public function fetchAssoc($result)
    {
        return $this->driver->fetchAssoc($result);
    }

    public function fetchRow($result)
    {
        return $this->driver->fetchRow($result);
    }

    public function fetchArray($result, $type = null)
    {
        return $this->driver->fetchArray($result, $type);
    }

    public function affectedRows()
    {
        return $this->driver->affectedRows();
    }

    public function escapeString(string $string)
    {
        $this->autoConnect();

        return $this->driver->escapeString($string);
    }

    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }

    public function freeResult($result)
    {
        return $this->driver->freeResult($result);
    }

    public function prepare(string $sql)
    {
        return $this->driver->prepare($sql);
    }

    public function isConnected()
    {
        return $this->isConnected;
    }

    public static function select(string $sql)
    {
        if (! IN_NEXUS) {
            $result = DB::select($sql);

            return json_decode(json_encode($result), true);
        }
        $res = self::getInstance()->query($sql);
        $result = [];
        while ($row = self::getInstance()->fetchAssoc($res)) {
            $result[] = $row;
        }

        return $result;
    }

    public static function bootEloquent(array $config)
    {
        $capsule = new Capsule(Container::getInstance());
        $connectionName = self::getConnectionName();
        $capsule->addConnection($config, $connectionName);
        // Capsule's constructor sets database.default to 'default', which would
        // break Laravel's DB facade and Eloquent when it runs after the kernel
        // has already loaded the real database config. Set the default to the
        // actual connection name so both managers resolve the same default.
        Container::getInstance()['config']['database.default'] = $connectionName;
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $connection = self::$eloquentConnection = $capsule->getConnection($connectionName);
        $connection->enableQueryLog();
        self::customModel();
    }

    public static function eloquentConnection(): Connection
    {
        if (self::$eloquentConnection !== null) {
            return self::$eloquentConnection;
        }

        return DB::connection(self::getConnectionName());
    }

    private static function schema(): Builder
    {
        if (IN_NEXUS) {
            return Capsule::schema(self::getConnectionName());
        }
        throw new \RuntimeException('can not call this when not in nexus.');
    }

    public static function hasTable($table): bool
    {
        if (IN_NEXUS) {
            return self::schema()->hasTable($table);
        }

        return Schema::hasTable($table);
    }

    public static function hasColumn($table, $column): bool
    {
        if (IN_NEXUS) {
            return self::schema()->hasColumn($table, $column);
        }

        return Schema::hasColumn($table, $column);
    }

    public static function table($table): \Illuminate\Database\Query\Builder
    {
        if (IN_NEXUS) {
            return Capsule::table($table, null, self::getConnectionName());
        }

        return DB::table($table);
    }

    public static function raw($value): Expression
    {
        if (IN_NEXUS) {
            return new Expression($value);
        }

        return DB::raw($value);
    }

    public static function statement($value)
    {
        if (IN_NEXUS) {
            return self::getInstance()->query($value);
        }

        return DB::statement($value);
    }

    public static function transaction(\Closure $callback, $attempts = 1)
    {
        if (IN_NEXUS) {
            return Capsule::connection(self::getConnectionName())->transaction($callback, $attempts);
        }

        return DB::transaction($callback, $attempts);
    }

    public static function remember($key, $ttl, \Closure $callback)
    {
        if (IN_NEXUS) {
            $Cache = SupportContext::getCache();
            if ($Cache === null) {
                return Cache::remember($key, $ttl, $callback);
            }
            $result = $Cache->get_value($key);
            if ($result === false) {
                $result = $callback();
                Logger::writeWithContext((string) "cache miss [{$key}]", (string) 'debug', (bool) false);
                $Cache->cache_value($key, $result, $ttl);
            } else {
                Logger::writeWithContext((string) "cache hit [{$key}]", (string) 'debug', (bool) false);
            }

            return $result;
        } else {
            return Cache::remember($key, $ttl, $callback);
        }
    }

    public static function cache_put($key, $value, $ttl = 3600)
    {
        if (IN_NEXUS) {
            $Cache = SupportContext::getCache();
            if ($Cache === null) {
                return Cache::put($key, $value, $ttl);
            }

            return $Cache->cache_value($key, $value, $ttl);
        } else {
            return Cache::put($key, $value, $ttl);
        }
    }

    public static function cache_get($key)
    {
        if (IN_NEXUS) {
            $Cache = SupportContext::getCache();
            if ($Cache === null) {
                return Cache::get($key);
            }

            return $Cache->get_value($key);
        } else {
            return Cache::get($key);
        }
    }

    public static function cache_del($key)
    {
        $Cache = SupportContext::getCache();
        if (IN_NEXUS && $Cache !== null) {
            $Cache->delete_value($key, true);

            return;
        }

        Cache::forget($key);
        $langList = Locale::available();
        foreach ($langList as $lf) {
            Cache::forget($lf.'_'.$key);
        }
    }

    public static function cache_del_by_pattern($pattern)
    {
        $redis = self::redis();
        $it = null;
        do {
            // Scan for some keys
            $arr_keys = $redis->scan($it, $pattern);

            // Redis may return empty results, so protect against that
            if ($arr_keys !== false) {
                foreach ($arr_keys as $str_key) {
                    Logger::writeWithContext((string) "[SCAN_KEY] {$str_key}", (string) 'info', (bool) false);
                    self::cache_del($str_key);
                }
            }
        } while ($it > 0);
    }

    /**
     * @return mixed|\Redis|null
     */
    public static function redis()
    {
        if (IN_NEXUS) {
            $Cache = SupportContext::getCache();
            if ($Cache === null) {
                return Redis::connection()->client();
            }

            return $Cache->getRedis();
        } else {
            return Redis::connection()->client();
        }
    }

    public static function getMysqlColumnInfo($table, $column = null)
    {
        static $driver;
        $config = Config::get('nexus.mysql', null);
        if (is_null($driver)) {
            $driver = new DBMysqli;
            $driver->connect($config['host'], $config['username'], $config['password'], 'information_schema', $config['port']);
        }
        $sql = sprintf(
            "select * from COLUMNS where TABLE_SCHEMA = '%s' and TABLE_NAME = '%s'",
            $config['database'], $table
        );
        if ($column !== null) {
            $sql .= " and COLUMN_NAME = '$column'";
        }
        $res = $driver->query($sql);
        if ($column !== null) {
            return $driver->fetchAssoc($res);
        }
        $results = [];
        while ($row = $driver->fetchAssoc($res)) {
            $results[$row['COLUMN_NAME']] = $row;
        }

        return $results;

    }

    public static function hasIndex($table, $indexName): bool
    {
        $results = self::select("show index from $table");
        foreach ($results as $item) {
            if ($item['Key_name'] == $indexName) {
                return true;
            }
        }

        return false;
    }

    public static function customModel(): void
    {
        if (class_exists(Passport::class)) {
            Passport::useClientModel(OauthClient::class);
        }
        if (class_exists(Sanctum::class)) {
            Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        }
    }

    public static function getConnectionName()
    {
        return Config::get('nexus.database.default', null);
    }

    public static function isMysql(): bool
    {
        return self::getConnectionName() === 'mysql';
    }

    public static function isPgsql(): bool
    {
        return self::getConnectionName() === 'pgsql';
    }

    public static function listColumnIndexNames(string $table, array $columns): array
    {
        $indexes = Schema::getIndexes($table);
        $indexesNames = [];
        foreach ($indexes as $index) {
            foreach ($columns as $columnName) {
                if (in_array($columnName, $index['columns'])) {
                    $indexesNames[] = $index['name'];
                    break;
                }
            }
        }

        return array_values(array_unique($indexesNames));
    }

    public static function getDatabaseVersionInfo(): array
    {
        $version = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        if (self::isMysql()) {
            $minVersion = '5.7.8';
        } elseif (self::isPgsql()) {
            $minVersion = '16.0';
        } else {
            throw new \RuntimeException('Not supported database.');
        }
        $dbType = self::getConnectionName();
        $match = version_compare($version, $minVersion, '>=');

        return compact('version', 'match', 'minVersion', 'dbType');
    }

    public static function unixTimestampField(string $field): string
    {
        if (self::isMysql()) {
            return sprintf('UNIX_TIMESTAMP(%s)', $field);
        } elseif (self::isPgsql()) {
            return sprintf('EXTRACT(EPOCH FROM %s)', $field);
        } else {
            throw new \RuntimeException('Not supported database.');
        }
    }

    public static function fromUnixTimestampField(int $timestamp): string
    {
        if (self::isMysql()) {
            return sprintf('FROM_UNIXTIME(%d)', $timestamp);
        } elseif (self::isPgsql()) {
            return sprintf('to_timestamp(%d)', $timestamp);
        } else {
            throw new \RuntimeException('Not supported database.');
        }
    }

    public static function upsertField(array $uniqueFields, array $updateFields = []): string
    {
        if (self::isMysql()) {
            $updates = [];
            foreach ($updateFields ?: ['id'] as $field) {
                $updates[] = "`$field` = VALUES(`$field`)";
            }

            return sprintf('ON DUPLICATE KEY UPDATE %s', implode(', ', $updates));
        } elseif (self::isPgsql()) {
            if (empty($updateFields)) {
                $updateStr = 'NOTHING';
            } else {
                $updates = [];
                foreach ($updateFields as $field) {
                    $updates[] = "$field = EXCLUDED.$field";
                }
                $updateStr = 'UPDATE SET '.implode(', ', $updates);
            }

            return sprintf('ON CONFLICT (%s) DO %s', implode(', ', $uniqueFields), $updateStr);
        } else {
            throw new \RuntimeException('Not supported database.');
        }
    }

    public static function groupConcatField(string $field): string
    {
        if (self::isMysql()) {
            return sprintf('group_concat(%s)', $field);
        } elseif (self::isPgsql()) {
            return sprintf("string_agg(%s::text, ',')", $field);
        } else {
            throw new \RuntimeException('Not supported database.');
        }
    }

    public static function binaryField(string $field): string
    {
        if (self::isMysql()) {
            return sprintf('%s = :%s', $field, $field);
        } elseif (self::isPgsql()) {
            return sprintf("%s = decode(:%s, 'hex')", $field, $field);
        } else {
            throw new \RuntimeException('Not supported database.');
        }
    }

    public static function binaryFieldBindValue($value): string
    {
        if (self::isMysql()) {
            return $value;
        } elseif (self::isPgsql()) {
            return bin2hex($value);
        } else {
            throw new \RuntimeException('Not supported database.');
        }
    }
}
