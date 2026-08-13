<?php
namespace App\Repositories;

use App\Enums\SeedBoxRecord\IpAsnEnum;
use App\Enums\SeedBoxRecord\IsAllowedEnum;
use App\Enums\SeedBoxRecord\TypeEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Models\Message;
use App\Models\Poll;
use App\Models\SeedBoxRecord;
use App\Models\User;
use GeoIp2\Database\Reader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Nexus\Database\NexusDB;
use PhpIP\IP;
use PhpIP\IPBlock;

class SeedBoxRepository extends BaseRepository
{
    const IS_SEED_BOX_FROM_USER_RECORD_CACHE_PREFIX = "IS_SEED_BOX_FROM_USER_RECORD";

    const APPROVAL_COUNT_CACHE_KEY = "SEED_BOX_RECORD_APPROVAL_NONE";

    /** @var  ?Reader */
    /** @var  ?Reader */
    /** @var  ?Reader */
    /** @var  ?Reader */
    /** @var  ?Reader */
    private static ?Reader $asnReader = null;

    /**
     * @param  array<int|string, mixed>  $params
     * @return  mixed
     */
    public function getList(array $params)
    {
        $query = Poll::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  SeedBoxRecord
     */
    public function store(array $params)
    {
        $params = $this->formatParams($params);
        $seedBoxRecord = SeedBoxRecord::query()->create($params);
        $this->clearApprovalCountCache();
        \App\Support\Events::publishModel("seed_box_record_created", $seedBoxRecord->id, "");
        return $seedBoxRecord;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  array<int|string, mixed>
     */
    private function formatParams(array $params): array
    {
        $params = array_filter($params);
        if (
            !empty($params['ip'])
            && empty($params['ip_begin'])
            && empty($params['ip_end'])
            && empty($params['asn'])
        ) {
            try {
                $ipBlock = IPBlock::create($params['ip']);
                $params['ip_begin_numeric'] = $ipBlock->getFirstIp()->numeric();
                $params['ip_end_numeric'] = $ipBlock->getLastIp()->numeric();
                $params['version'] = $ipBlock->getVersion();
            } catch (\Exception $exception) {
                \App\Support\Logger::writeWithContext((string) ("[NOT_IP_BLOCK], {$params['ip']}" . $exception->getMessage()), (string) 'info', (bool) false);
            }
            if (empty($params['version'])) {
                try {
                    $ip = IP::create($params['ip']);
                    $params['ip_begin_numeric'] = $ip->numeric();
                    $params['ip_end_numeric'] = $ip->numeric();
                    $params['version'] = $ip->getVersion();
                } catch (\Exception $exception) {
                    \App\Support\Logger::writeWithContext((string) ("[NOT_IP], {$params['ip']}" . $exception->getMessage()), (string) 'info', (bool) false);
                }
            }
            if (empty($params['version'])) {
                throw new \InvalidArgumentException("Invalid IPBlock or IP: " . $params['ip']);
            }
        } elseif (
            empty($params['ip'])
            && empty($params['asn'])
            && !empty($params['ip_begin'])
            && !empty($params['ip_end'])
        ) {
            $ipBegin = IP::create($params['ip_begin']);
            $params['ip_begin_numeric'] = $ipBegin->numeric();

            $ipEnd = IP::create($params['ip_end']);
            $params['ip_end_numeric'] = $ipEnd->numeric();
            if ($ipBegin->getVersion() != $ipEnd->getVersion()) {
                throw new \InvalidArgumentException("ip_begin/ip_end must be the same version");
            }
            $params['version'] = $ipEnd->getVersion();
        } elseif (
            !empty($params['asn'])
            && empty($params['ip'])
            && empty($params['ip_begin'])
            && empty($params['ip_end'])
        ) {
            \App\Support\Logger::writeWithContext((string) ("only asn: " . $params['asn']), (string) 'info', (bool) false);
        } else {
            throw new \InvalidArgumentException(\App\Support\Locale::trans('label.seed_box_record.ip_help', [], null));
        }

        return $params;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $id
     * @return  mixed
     */
    public function update(array $params, $id)
    {
        $model = SeedBoxRecord::query()->findOrFail($id);
        $params = $this->formatParams($params);
        $model->update($params);
        $this->clearApprovalCountCache();
        \App\Support\Events::publishModel("seed_box_record_updated", $id, "");
        return $model;
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function getDetail($id)
    {
        $model = Poll::query()->findOrFail($id);
        return $model;
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $uid
     * @return  mixed
     */
    public function delete($id, $uid)
    {
        $baseQuery =  SeedBoxRecord::query()->whereIn('id', Arr::wrap($id))->where('uid', $uid);
        $list = $baseQuery->clone()->get();
        if ($list->isEmpty()) {
            return false;
        }
        $baseQuery->delete();
        $this->clearApprovalCountCache();
        foreach ($list as $record) {
            \App\Support\Events::publishModel("seed_box_record_deleted", $record->id, $record->toJson());
        }
        return true;
    }

    /**
     * @param  \App\Models\SeedBoxRecord  $seedBoxRecord
     * @param  mixed  $status
     * @param  mixed  $reason
     * @return  mixed
     */
    public function updateStatus(SeedBoxRecord $seedBoxRecord, $status, $reason = '')
    {
        if (Auth::user()->class < User::CLASS_ADMINISTRATOR) {
            throw new InsufficientPermissionException();
        }
        if (!isset(SeedBoxRecord::$status[$status])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        if ($seedBoxRecord->status == $status) {
            return true;
        }
        $message = [
            'receiver' => $seedBoxRecord->uid,
            'subject' => \App\Support\Locale::trans('seed-box.status_change_message.subject', [], null),
            'msg' => \App\Support\Locale::trans('seed-box.status_change_message.body', ['id' => $seedBoxRecord->id, 'operator' => Auth::user()->username, 'old_status' => $seedBoxRecord->statusText, 'new_status' => \App\Support\Locale::trans('seed-box.status_text.' . $status, [], null), 'reason' => $reason], null),
            'added' => now()
        ];
        return NexusDB::transaction(function () use ($seedBoxRecord, $status, $message) {
            $seedBoxRecord->status = $status;
            $seedBoxRecord->save();
            $this->clearApprovalCountCache();
            return Message::add($message);
        });
    }

    /**
     * @param  mixed  $ipArr
     * @param  mixed  $uid
     */
    public function renderIcon($ipArr, $uid): string
    {
        static $enableSeedBox;
        if ($enableSeedBox === null) {
            $enableSeedBox = \App\Support\Config\SiteConfig::current()->seedBox->enabled();
        }
        foreach (Arr::wrap($ipArr) as $ip) {
            if ((\App\Support\Network::isIpv4($ip) || \App\Support\Network::isIpv6($ip)) && $enableSeedBox && \App\Support\Network::isSeedBox((string) $ip, (int) $uid)) {
                return $this->getSeedBoxIcon();
            }
        }
        return '';
    }

    /** @param  mixed  $isSeedBox */
    public function getSeedBoxIcon($isSeedBox = true): string
    {
        if (!$isSeedBox) {
            return '';
        }
        return '<img src="pic/misc/seed-box.png" style="vertical-align: bottom; height: 16px; margin-left: 4px" title="SeedBox" />';
    }

    private function clearApprovalCountCache(): void
    {
        NexusDB::cache_del(self::APPROVAL_COUNT_CACHE_KEY);
    }

    /**
     * @param  \App\Enums\SeedBoxRecord\IsAllowedEnum  $isAllowed
     * @param  \App\Enums\SeedBoxRecord\IpAsnEnum  $field
     */
    public function updateUserCacheCronjob(IsAllowedEnum $isAllowed, IpAsnEnum $field): void
    {
        $size = 1000;
        $page = 1;
        $logPrefix = "isAllowed: $isAllowed->name, field: $field->name, page: $page, size: $size";
        $selectRaw = sprintf("uid, %s as str", NexusDB::groupConcatField($field == IpAsnEnum::ASN ? 'asn' : 'ip'));
        while (true) {
            $list = SeedBoxRecord::getValidQuery(TypeEnum::USER, $isAllowed, $field)
                ->selectRaw($selectRaw)
                ->groupBy('uid')
                ->forPage($page, $size)
                ->get();
            if ($list->isEmpty()) {
                \App\Support\Logger::writeWithContext((string) "{$logPrefix}, no more data ...", (string) 'info', (bool) false);
                break;
            }
            foreach ($list as $record) {
                $uid = $record->uid;
                $str = $record->str;
                \App\Support\Logger::writeWithContext((string) "{$logPrefix}, handling user: {$uid} with {$field->name}: {$str}", (string) 'info', (bool) false);
                self::updateCache($record->uid, TypeEnum::USER, $isAllowed, $field, $str);
                \App\Support\Logger::writeWithContext((string) "{$logPrefix}, handling user: {$uid} with {$field->name}: {$str} done!", (string) 'info', (bool) false);
            }
            $page++;
        }
        \App\Support\Logger::writeWithContext((string) "{$logPrefix}, all done!", (string) 'info', (bool) false);
    }

    /**
     * @param  \App\Enums\SeedBoxRecord\IsAllowedEnum  $isAllowed
     * @param  \App\Enums\SeedBoxRecord\IpAsnEnum  $field
     */
    public function updateAdminCacheCronjob(IsAllowedEnum $isAllowed, IpAsnEnum $field): void
    {
        $size = 1000;
        $page = 1;
        $logPrefix = "isAllowed: $isAllowed->name, field: $field->name, page: $page, size: $size";
        $fieldName = $field == IpAsnEnum::ASN ? 'asn' : 'ip';
        while (true) {
            $list = SeedBoxRecord::getValidQuery(TypeEnum::ADMIN, $isAllowed, $field)
                ->selectRaw($fieldName)
                ->forPage($page, $size)
                ->get();
            if ($list->isEmpty()) {
                \App\Support\Logger::writeWithContext((string) "{$logPrefix}, no more data ...", (string) 'info', (bool) false);
                break;
            }
            self::updateCache(0, TypeEnum::ADMIN, $isAllowed, $field, $list->pluck($fieldName)->join(","));
            $page++;
        }
        \App\Support\Logger::writeWithContext((string) "{$logPrefix}, all done!", (string) 'info', (bool) false);
    }

    /**
     * @param  int  $userId
     * @param  \App\Enums\SeedBoxRecord\TypeEnum  $type
     * @param  \App\Enums\SeedBoxRecord\IsAllowedEnum  $isAllowed
     * @param  \App\Enums\SeedBoxRecord\IpAsnEnum  $field
     * @param  string  $ipOrAsnStr
     */
    public static function updateCache(int $userId, TypeEnum $type, IsAllowedEnum $isAllowed, IpAsnEnum $field, ?string $ipOrAsnStr = null): void
    {
        if (!is_null($ipOrAsnStr)) {
            $list = explode(',', $ipOrAsnStr);
        } else {
            $query = SeedBoxRecord::getValidQuery($type, $isAllowed, $field);
            if ($userId > 0) {
                $query->where('uid', $userId);
            }
            if ($field == IpAsnEnum::IP) {
                $list = $query->pluck('ip')->toArray();
            } else {
                $list = $query->pluck('asn')->toArray();
            }
        }
        $list = array_filter($list);
        $key = self::getCacheKey($userId, $isAllowed, $field);
        \App\Support\Logger::writeWithContext((string) ("userId: {$userId}, type: {$type->name}, isAllowed: {$isAllowed->name}, ipOrAsn: {$field->name}, key: {$key}, list: " . json_encode($list)), (string) 'info', (bool) false);
        NexusDB::cache_del($key);
        if (!empty($list)) {
            NexusDB::redis()->sadd($key, ...$list);
            NexusDB::redis()->expireAt($key, time() + 86400 * 30);
        }
    }

    /**
     * @param  int  $userId
     * @param  string  $ip
     * @return  array<int|string, mixed>
     */
    public static function isSeedBoxFromUserRecords(int $userId, string $ip): array
    {
        $logPrefix = "userId: $userId, ip: $ip";
        $redis = NexusDB::redis();
        //first check from asn field
        $asn = self::getAsnFromIp($ip);
        $uidArr = [0, $userId];
        if ($asn > 0) {
            //check if allowed by asn
            $logPrefix .= ", asn: $asn";
            foreach($uidArr as $uid) {
                $key = self::getCacheKey($uid, IsAllowedEnum::YES, IpAsnEnum::ASN);
                if ($redis->sismember($key, $asn)) {
                    $desc = "$logPrefix, asn $asn in allowed $key, result: false";
                    return self::buildCheckResult(false, $desc);
                }
            }
            foreach($uidArr as $uid) {
                $key = self::getCacheKey($uid, IsAllowedEnum::NO, IpAsnEnum::ASN);
                if ($redis->sismember($key, $asn)) {
                    $desc = ("$logPrefix, asn $asn in $key, result: true");
                    return self::buildCheckResult(true, $desc);
                }
            }
        }
        //then check from ip field
        foreach($uidArr as $uid) {
            $key = self::getCacheKey($uid, IsAllowedEnum::YES, IpAsnEnum::IP);
            if ($redis->sismember($key, $ip)) {
                $desc = ("$logPrefix, ip $ip in allowed $key, result: false");
                return self::buildCheckResult(false, $desc);
            }
        }
        foreach($uidArr as $uid) {
            $key = self::getCacheKey($uid, IsAllowedEnum::NO, IpAsnEnum::IP);
            if ($redis->sismember($key, $ip)) {
                $desc = ("$logPrefix, ip $ip in $key, result: true");
                return self::buildCheckResult(true, $desc);
            }
        }
        return self::buildCheckResult(false, "not match any record, result: false");
    }

    /**
     * @param  bool  $isSeedBox
     * @param  string  $desc
     * @return  array<int|string, mixed>
     */
    private static function buildCheckResult(bool $isSeedBox, string $desc): array
    {
        $result = [
            'result' => $isSeedBox,
            'desc' => $desc,
        ];
        \App\Support\Logger::writeWithContext((string) json_encode($result), (string) 'info', (bool) false);
        return $result;
    }

    /** @param  string  $ip */
    public static function getAsnFromIp(string $ip): int
    {
        //虽然 ip 对应的 asn 相对固定，但不宜设置较大的缓存时间，IP 地址较多，容易引起内存膨胀
        return NexusDB::remember("IP_TO_ASN:$ip", 3600, function () use ($ip) {
            $reader = self::getAsnReader();
            if (is_null($reader)) {
                return 0;
            }
            try {
                $asnObj = $reader->asn($ip);
                return $asnObj->autonomousSystemNumber ?? 0;
            } catch (\Exception $e) {
                \App\Support\Logger::writeWithContext((string) ("ip: {$ip}, error: " . $e->getMessage()), (string) 'info', (bool) false);
                return 0;
            }
        });
    }

    /**
     * IS_SEED_BOX_FROM_USER_RECORD:IP:INCLUDES:USER:10001
     * IS_SEED_BOX_FROM_USER_RECORD:IP:EXCLUDES:USER:10001
     * IS_SEED_BOX_FROM_USER_RECORD:ASN:INCLUDES:USER:10001
     * IS_SEED_BOX_FROM_USER_RECORD:ASN:EXCLUDES:USER:10001
     * IS_SEED_BOX_FROM_USER_RECORD:ASN:EXCLUDES:ADMIN
     * @param  int  $userId
     * @param  \App\Enums\SeedBoxRecord\IsAllowedEnum  $isAllowed
     * @param  \App\Enums\SeedBoxRecord\IpAsnEnum  $field
     * @return  string
     */
    private static function getCacheKey(int $userId, IsAllowedEnum $isAllowed, IpAsnEnum $field): string
    {
        $key = sprintf(
            "%s:%s:%s",
            self::IS_SEED_BOX_FROM_USER_RECORD_CACHE_PREFIX,
            $field->name,
            $isAllowed == IsAllowedEnum::YES ? "EXCLUDES" : "INCLUDES", //允许，要排队它不是 seedBox
        );
        if ($userId > 0) {
            $key .= ":USER:$userId";
        } else {
            $key .= ":ADMIN";
        }
        return $key;
    }

    /** @throws InvalidDatabaseException */
    private static function getAsnReader(): ?Reader
    {
        if (is_null(self::$asnReader)) {
            $database = \App\Support\Env::get('GEOIP2_ASN_DATABASE', null);
            if (!file_exists($database) || !is_readable($database)) {
                \App\Support\Logger::writeWithContext((string) "GEOIP2_ASN_DATABASE: {$database} not exists or not readable", (string) "debug", (bool) false);
                return null;
            }
            self::$asnReader = new Reader($database);
        }
        return self::$asnReader;
    }


}
