<?php

/**
 * @property int $id
 * @property int $type
 * @property int $uid
 * @property int $status
 * @property string|null $operator
 * @property int|null $bandwidth
 * @property string|null $ip
 * @property string|null $ip_begin
 * @property string|null $ip_end
 * @property string $ip_begin_numeric
 * @property string $ip_end_numeric
 * @property int $version
 * @property string|null $comment
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int $is_allowed
 * @property int $asn
 */

namespace App\Models;

use App\Enums\SeedBoxRecord\IpAsnEnum;
use App\Enums\SeedBoxRecord\IsAllowedEnum;
use App\Enums\SeedBoxRecord\TypeEnum;
use App\Models\Traits\NexusActivityLogTrait;
use App\Repositories\SeedBoxRepository;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $typeText
 * @property string $ipRange
 * @property string $statusText
 */
class SeedBoxRecord extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = ['type', 'uid', 'status', 'operator', 'bandwidth', 'ip', 'ip_begin', 'ip_end', 'ip_begin_numeric', 'ip_end_numeric',
        'comment', 'version', 'is_allowed', 'asn',
    ];

    /** @var bool */
    public $timestamps = true;

    const TYPE_USER = 1;

    const TYPE_ADMIN = 2;

    /** @var array<int|string, mixed> */
    public static array $types = [
        self::TYPE_USER => ['text' => 'User'],
        self::TYPE_ADMIN => ['text' => 'Administrator'],
    ];

    const STATUS_UNAUDITED = 0;

    const STATUS_ALLOWED = 1;

    const STATUS_DENIED = 2;

    /** @var array<int|string, mixed> */
    public static array $status = [
        self::STATUS_UNAUDITED => ['text' => 'Unaudited'],
        self::STATUS_ALLOWED => ['text' => 'Allowed'],
        self::STATUS_DENIED => ['text' => 'Denied'],
    ];

    protected static function booted(): void
    {
        static::saved(function (SeedBoxRecord $model) {
            self::updateCache($model);
        });
        static::deleted(function (SeedBoxRecord $model) {
            self::updateCache($model);
        });
    }

    private static function updateCache(SeedBoxRecord $model): void
    {
        SeedBoxRepository::updateCache(
            $model->type == TypeEnum::ADMIN->value ? 0 : $model->uid,
            TypeEnum::from($model->type),
            IsAllowedEnum::from($model->is_allowed),
            ! empty($model->ip) ? IpAsnEnum::IP : IpAsnEnum::ASN,
        );
    }

    /**
     * @return mixed
     */
    public static function getValidQuery(TypeEnum $type, IsAllowedEnum $isAllowed, IpAsnEnum $field)
    {
        $query = self::query()
            ->where('status', self::STATUS_ALLOWED)
            ->where('type', $type->value)
            ->where('is_allowed', $isAllowed->value);
        if ($field == IpAsnEnum::IP) {
            $query->whereNotNull('ip');
        } elseif ($field == IpAsnEnum::ASN) {
            $query->where('asn', '>', 0);
        } else {
            throw new \InvalidArgumentException('Invalid ipOrAsn');
        }

        return $query;
    }

    /** @return  Attribute<mixed, mixed> */
    protected function typeText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => Locale::trans('seed-box.type_text.'.$attributes['type'], [], null)
        );
    }

    /** @return  Attribute<mixed, mixed> */
    protected function ipRange(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => $attributes['ip'] ?: sprintf('%s ~ %s', $attributes['ip_begin'] ?? '', $attributes['ip_end'] ?? ''),
        );
    }

    /** @return  Attribute<mixed, mixed> */
    protected function statusText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => Locale::trans('seed-box.status_text.'.$attributes['status'], [], null)
        );
    }

    /**
     * @param  mixed  $key
     * @return array<int|string, mixed>
     */
    public static function listTypes($key = null): array
    {
        $result = self::$types;
        $keyValues = [];
        foreach ($result as $type => &$info) {
            $info['text'] = Locale::trans("seed-box.type_text.{$type}", [], null);
            if ($key !== null) {
                $keyValues[$type] = $info[$key];
            }
        }

        return $key === null ? $result : $keyValues;
    }

    /**
     * @param  mixed  $key
     * @return array<int|string, mixed>
     */
    public static function listStatus($key = null): array
    {
        $result = self::$status;
        $keyValues = [];
        foreach ($result as $status => &$info) {
            $info['text'] = Locale::trans("seed-box.status_text.{$status}", [], null);
            if ($key !== null) {
                $keyValues[$status] = $info[$key];
            }
        }

        return $key === null ? $result : $keyValues;
    }

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }
}
