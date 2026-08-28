<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $business_type
 * @property int $uid
 * @property float $old_total_value
 * @property float $value
 * @property float $new_total_value
 * @property string|null $comment
 * @property string $created_at
 * @property string $updated_at
 */

namespace App\Models;

use App\Enums\BusinessType;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use Carbon\Carbon;

class BonusLogs extends NexusModel
{
    /** @var string */
    protected $table = 'bonus_logs';

    /** @var list<string> */
    protected $fillable = ['uid', 'business_type', 'old_total_value', 'value', 'new_total_value', 'comment', 'created_at', 'updated_at'];

    /** @var bool */
    public $timestamps = true;

    const CATEGORY_COMMON = 'common';

    const DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN = 10000;

    const DEFAULT_BONUS_BUY_ATTENDANCE_CARD = 1000;

    const DEFAULT_BONUS_BUY_TEMPORARY_INVITE = 500;

    const DEFAULT_BONUS_BUY_RAINBOW_ID = 5000;

    const DEFAULT_BONUS_BUY_CHANGE_USERNAME_CARD = 100000;

    const DEFAULT_BONUS_SELF_ENABLE = 100000;

    // 扣除类，1开始
    const BUSINESS_TYPE_CANCEL_HIT_AND_RUN = 1;

    const BUSINESS_TYPE_BUY_MEDAL = 2;

    const BUSINESS_TYPE_BUY_ATTENDANCE_CARD = 3;

    const BUSINESS_TYPE_STICKY_PROMOTION = 4;

    const BUSINESS_TYPE_POST_REWARD = 5;

    const BUSINESS_TYPE_EXCHANGE_UPLOAD = 6;

    const BUSINESS_TYPE_EXCHANGE_INVITE = 7;

    const BUSINESS_TYPE_CUSTOM_TITLE = 8;

    const BUSINESS_TYPE_BUY_VIP = 9;

    const BUSINESS_TYPE_GIFT_TO_SOMEONE = 10;

    const BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO = 12;

    const BUSINESS_TYPE_LUCKY_DRAW = 13;

    const BUSINESS_TYPE_EXCHANGE_DOWNLOAD = 14;

    const BUSINESS_TYPE_BUY_TEMPORARY_INVITE = 15;

    const BUSINESS_TYPE_BUY_RAINBOW_ID = 16;

    const BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD = 17;

    const BUSINESS_TYPE_GIFT_MEDAL = 18;

    const BUSINESS_TYPE_BUY_TORRENT = 19;

    const BUSINESS_TYPE_TASK_NOT_PASS_DEDUCT = 20;

    const BUSINESS_TYPE_TASK_PASS_REWARD = 21;

    const BUSINESS_TYPE_REWARD_TORRENT = 22;

    const BUSINESS_TYPE_SELF_ENABLE = 24;

    // 获得类，普通获得，1000 起步
    const BUSINESS_TYPE_ROLE_WORK_SALARY = 1000;

    const BUSINESS_TYPE_TORRENT_BE_DOWNLOADED = 1001;

    const BUSINESS_TYPE_RECEIVE_REWARD = 1002;

    const BUSINESS_TYPE_RECEIVE_GIFT = 1003;

    const BUSINESS_TYPE_UPLOAD_TORRENT = 1004;

    const BUSINESS_TYPE_TORRENT_BE_REWARD = 1005;

    /** @var array<int|string, mixed> */
    public static array $businessTypes = [
        self::BUSINESS_TYPE_CANCEL_HIT_AND_RUN => ['text' => 'Cancel H&R'],
        self::BUSINESS_TYPE_BUY_MEDAL => ['text' => 'Buy medal'],
        self::BUSINESS_TYPE_BUY_ATTENDANCE_CARD => ['text' => 'Buy attendance card'],
        self::BUSINESS_TYPE_STICKY_PROMOTION => ['text' => 'Buy torrent sticky promotion'],
        self::BUSINESS_TYPE_POST_REWARD => ['text' => 'Reward post'],
        self::BUSINESS_TYPE_EXCHANGE_UPLOAD => ['text' => 'Exchange upload'],
        self::BUSINESS_TYPE_EXCHANGE_INVITE => ['text' => 'Exchange invite'],
        self::BUSINESS_TYPE_CUSTOM_TITLE => ['text' => 'Custom title'],
        self::BUSINESS_TYPE_BUY_VIP => ['text' => 'Buy VIP'],
        self::BUSINESS_TYPE_GIFT_TO_SOMEONE => ['text' => 'Gift to someone'],
        self::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO => ['text' => 'Gift to low share ratio'],
        self::BUSINESS_TYPE_LUCKY_DRAW => ['text' => 'Lucky draw'],
        self::BUSINESS_TYPE_EXCHANGE_DOWNLOAD => ['text' => 'Exchange download'],
        self::BUSINESS_TYPE_BUY_TEMPORARY_INVITE => ['text' => 'Buy temporary invite'],
        self::BUSINESS_TYPE_BUY_RAINBOW_ID => ['text' => 'Buy rainbow ID'],
        self::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD => ['text' => 'Buy change username card'],
        self::BUSINESS_TYPE_GIFT_MEDAL => ['text' => 'Gift medal to someone'],
        self::BUSINESS_TYPE_BUY_TORRENT => ['text' => 'Buy torrent'],
        self::BUSINESS_TYPE_TASK_NOT_PASS_DEDUCT => ['text' => 'Task failure penalty'],
        self::BUSINESS_TYPE_TASK_PASS_REWARD => ['text' => 'Task success reward'],
        self::BUSINESS_TYPE_REWARD_TORRENT => ['text' => 'Reward torrent'],
        self::BUSINESS_TYPE_SELF_ENABLE => ['text' => 'Self enable'],

        self::BUSINESS_TYPE_ROLE_WORK_SALARY => ['text' => 'Role work salary'],
        self::BUSINESS_TYPE_TORRENT_BE_DOWNLOADED => ['text' => 'Torrent be downloaded'],
        self::BUSINESS_TYPE_RECEIVE_REWARD => ['text' => 'Receive reward'],
        self::BUSINESS_TYPE_RECEIVE_GIFT => ['text' => 'Receive gift'],
        self::BUSINESS_TYPE_UPLOAD_TORRENT => ['text' => 'Upload torrent'],
        self::BUSINESS_TYPE_TORRENT_BE_REWARD => ['text' => 'Torrent be reward'],
    ];

    /**
     * @param  mixed  $category
     * @return array<int|string, mixed>
     */
    public static function listBusinessTypeOptions($category = ''): array
    {
        $source = BonusLogs::$businessTypes;
        if ($category == self::CATEGORY_COMMON) {
            $source = BonusLogs::$businessTypes;
        }

        return self::listStaticProps($source, 'bonus-log.business_types', true);
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function listCategoryOptions(): array
    {
        return [
            self::CATEGORY_COMMON => Locale::trans('bonus-log.category_common', [], null),
        ];
    }

    /** @return  mixed */
    public function getBusinessTypeTextAttribute()
    {
        return Locale::trans('bonus-log.business_types.'.$this->business_type, [], null);
    }

    public static function getBonusForCancelHitAndRun(): float
    {
        return SiteConfig::current()->bonus->cancelHr(self::DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN);
    }

    public static function getBonusForBuyAttendanceCard(): float
    {
        return SiteConfig::current()->bonus->attendanceCard(self::DEFAULT_BONUS_BUY_ATTENDANCE_CARD);
    }

    public static function getBonusForBuyTemporaryInvite(): float
    {
        return SiteConfig::current()->bonus->oneTmpInvite(self::DEFAULT_BONUS_BUY_TEMPORARY_INVITE);
    }

    public static function getBonusForBuyRainbowId(): float
    {
        return SiteConfig::current()->bonus->rainbowId(self::DEFAULT_BONUS_BUY_RAINBOW_ID);
    }

    public static function getBonusForBuyChangeUsernameCard(): float
    {
        return SiteConfig::current()->bonus->changeUsernameCard(self::DEFAULT_BONUS_BUY_CHANGE_USERNAME_CARD);
    }

    /**
     * @return mixed
     */
    public static function add(int $userId, float $old, float $delta, float $new, string $comment, int $businessType)
    {
        $enum = BusinessType::fromIntSafe($businessType);
        if ($enum === null) {
            throw new \InvalidArgumentException("Invalid business type: $businessType");
        }
        $nowStr = Carbon::now()->toDateTimeString();

        return self::query()->create([
            'business_type' => $businessType,
            'uid' => $userId,
            'old_total_value' => $old,
            'value' => $delta,
            'new_total_value' => $new,
            'comment' => sprintf('[%s]%s', $enum->label(), $comment ? " $comment" : ''),
            'created_at' => $nowStr,
            'updated_at' => $nowStr,
        ]);
    }
}
