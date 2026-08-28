<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for bonus-log business types.
 *
 * Mirrors the integer constants from App\Models\BonusLogs.
 * Values 1–24 are deduction-type operations; values 1000+ are income-type.
 */
enum BusinessType: int
{
    // Deduction types (1–24)
    case CANCEL_HIT_AND_RUN = 1;
    case BUY_MEDAL = 2;
    case BUY_ATTENDANCE_CARD = 3;
    case STICKY_PROMOTION = 4;
    case POST_REWARD = 5;
    case EXCHANGE_UPLOAD = 6;
    case EXCHANGE_INVITE = 7;
    case CUSTOM_TITLE = 8;
    case BUY_VIP = 9;
    case GIFT_TO_SOMEONE = 10;
    case GIFT_TO_LOW_SHARE_RATIO = 12;
    case LUCKY_DRAW = 13;
    case EXCHANGE_DOWNLOAD = 14;
    case BUY_TEMPORARY_INVITE = 15;
    case BUY_RAINBOW_ID = 16;
    case BUY_CHANGE_USERNAME_CARD = 17;
    case GIFT_MEDAL = 18;
    case BUY_TORRENT = 19;
    case TASK_NOT_PASS_DEDUCT = 20;
    case TASK_PASS_REWARD = 21;
    case REWARD_TORRENT = 22;
    case SELF_ENABLE = 24;

    // Income types (1000+)
    case ROLE_WORK_SALARY = 1000;
    case TORRENT_BE_DOWNLOADED = 1001;
    case RECEIVE_REWARD = 1002;
    case RECEIVE_GIFT = 1003;
    case UPLOAD_TORRENT = 1004;
    case TORRENT_BE_REWARD = 1005;

    /**
     * Default English display text for each business type.
     */
    public function label(): string
    {
        return match ($this) {
            self::CANCEL_HIT_AND_RUN => 'Cancel H&R',
            self::BUY_MEDAL => 'Buy medal',
            self::BUY_ATTENDANCE_CARD => 'Buy attendance card',
            self::STICKY_PROMOTION => 'Buy torrent sticky promotion',
            self::POST_REWARD => 'Reward post',
            self::EXCHANGE_UPLOAD => 'Exchange upload',
            self::EXCHANGE_INVITE => 'Exchange invite',
            self::CUSTOM_TITLE => 'Custom title',
            self::BUY_VIP => 'Buy VIP',
            self::GIFT_TO_SOMEONE => 'Gift to someone',
            self::GIFT_TO_LOW_SHARE_RATIO => 'Gift to low share ratio',
            self::LUCKY_DRAW => 'Lucky draw',
            self::EXCHANGE_DOWNLOAD => 'Exchange download',
            self::BUY_TEMPORARY_INVITE => 'Buy temporary invite',
            self::BUY_RAINBOW_ID => 'Buy rainbow ID',
            self::BUY_CHANGE_USERNAME_CARD => 'Buy change username card',
            self::GIFT_MEDAL => 'Gift medal to someone',
            self::BUY_TORRENT => 'Buy torrent',
            self::TASK_NOT_PASS_DEDUCT => 'Task failure penalty',
            self::TASK_PASS_REWARD => 'Task success reward',
            self::REWARD_TORRENT => 'Reward torrent',
            self::SELF_ENABLE => 'Self enable',
            self::ROLE_WORK_SALARY => 'Role work salary',
            self::TORRENT_BE_DOWNLOADED => 'Torrent be downloaded',
            self::RECEIVE_REWARD => 'Receive reward',
            self::RECEIVE_GIFT => 'Receive gift',
            self::UPLOAD_TORRENT => 'Upload torrent',
            self::TORRENT_BE_REWARD => 'Torrent be reward',
        };
    }

    /**
     * Whether this is a deduction-type operation (user spends bonus).
     */
    public function isDeduction(): bool
    {
        return $this->value < 1000;
    }

    /**
     * Whether this is an income-type operation (user receives bonus).
     */
    public function isIncome(): bool
    {
        return $this->value >= 1000;
    }

    /**
     * Resolve a business type from an integer, falling back to null for invalid values.
     */
    public static function fromIntSafe(int $value): ?self
    {
        return self::tryFrom($value);
    }
}
