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

    /** @var array<int|string, mixed> */
    public static array $businessTypes = [
        BusinessType::CANCEL_HIT_AND_RUN->value => ['text' => 'Cancel H&R'],
        BusinessType::BUY_MEDAL->value => ['text' => 'Buy medal'],
        BusinessType::BUY_ATTENDANCE_CARD->value => ['text' => 'Buy attendance card'],
        BusinessType::STICKY_PROMOTION->value => ['text' => 'Buy torrent sticky promotion'],
        BusinessType::POST_REWARD->value => ['text' => 'Reward post'],
        BusinessType::EXCHANGE_UPLOAD->value => ['text' => 'Exchange upload'],
        BusinessType::EXCHANGE_INVITE->value => ['text' => 'Exchange invite'],
        BusinessType::CUSTOM_TITLE->value => ['text' => 'Custom title'],
        BusinessType::BUY_VIP->value => ['text' => 'Buy VIP'],
        BusinessType::GIFT_TO_SOMEONE->value => ['text' => 'Gift to someone'],
        BusinessType::GIFT_TO_LOW_SHARE_RATIO->value => ['text' => 'Gift to low share ratio'],
        BusinessType::LUCKY_DRAW->value => ['text' => 'Lucky draw'],
        BusinessType::EXCHANGE_DOWNLOAD->value => ['text' => 'Exchange download'],
        BusinessType::BUY_TEMPORARY_INVITE->value => ['text' => 'Buy temporary invite'],
        BusinessType::BUY_RAINBOW_ID->value => ['text' => 'Buy rainbow ID'],
        BusinessType::BUY_CHANGE_USERNAME_CARD->value => ['text' => 'Buy change username card'],
        BusinessType::GIFT_MEDAL->value => ['text' => 'Gift medal to someone'],
        BusinessType::BUY_TORRENT->value => ['text' => 'Buy torrent'],
        BusinessType::TASK_NOT_PASS_DEDUCT->value => ['text' => 'Task failure penalty'],
        BusinessType::TASK_PASS_REWARD->value => ['text' => 'Task success reward'],
        BusinessType::REWARD_TORRENT->value => ['text' => 'Reward torrent'],
        BusinessType::SELF_ENABLE->value => ['text' => 'Self enable'],

        BusinessType::ROLE_WORK_SALARY->value => ['text' => 'Role work salary'],
        BusinessType::TORRENT_BE_DOWNLOADED->value => ['text' => 'Torrent be downloaded'],
        BusinessType::RECEIVE_REWARD->value => ['text' => 'Receive reward'],
        BusinessType::RECEIVE_GIFT->value => ['text' => 'Receive gift'],
        BusinessType::UPLOAD_TORRENT->value => ['text' => 'Upload torrent'],
        BusinessType::TORRENT_BE_REWARD->value => ['text' => 'Torrent be reward'],
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
