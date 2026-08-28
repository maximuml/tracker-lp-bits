<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for the legacy 0–16 user-class ladder.
 *
 * Mirrors the integer constants from App\Support\UserClass and the
 * string constants from App\Models\User, unifying them into a single
 * type-safe source of truth.
 *
 * Tier order (ascending): Peasant → User → Power User → … → Staff Leader.
 */
enum UserClass: int
{
    case PEASANT = 0;
    case USER = 1;
    case POWER_USER = 2;
    case ELITE_USER = 3;
    case CRAZY_USER = 4;
    case INSANE_USER = 5;
    case VETERAN_USER = 6;
    case EXTREME_USER = 7;
    case ULTIMATE_USER = 8;
    case NEXUS_MASTER = 9;
    case VIP = 10;
    case RETIREE = 11;
    case UPLOADER = 12;
    case MODERATOR = 13;
    case ADMINISTRATOR = 14;
    case SYSOP = 15;
    case STAFFLEADER = 16;

    /**
     * Default English display text for each tier.
     */
    public function label(): string
    {
        return match ($this) {
            self::PEASANT => 'Peasant',
            self::USER => 'User',
            self::POWER_USER => 'Power User',
            self::ELITE_USER => 'Elite User',
            self::CRAZY_USER => 'Crazy User',
            self::INSANE_USER => 'Insane User',
            self::VETERAN_USER => 'Veteran User',
            self::EXTREME_USER => 'Extreme User',
            self::ULTIMATE_USER => 'Ultimate User',
            self::NEXUS_MASTER => 'Nexus Master',
            self::VIP => 'VIP',
            self::RETIREE => 'Retiree',
            self::UPLOADER => 'Uploader',
            self::MODERATOR => 'Moderator',
            self::ADMINISTRATOR => 'Administrator',
            self::SYSOP => 'Sysop',
            self::STAFFLEADER => 'Staff Leader',
        };
    }

    /**
     * Lang-array key used by the legacy language files.
     *
     * Note the deliberately pluralised keys for staff tiers
     * (text_moderators, text_administrators, text_sysops) —
     * those match the legacy lang files exactly.
     */
    public function langKey(): string
    {
        return match ($this) {
            self::PEASANT => 'text_peasant',
            self::USER => 'text_user',
            self::POWER_USER => 'text_power_user',
            self::ELITE_USER => 'text_elite_user',
            self::CRAZY_USER => 'text_crazy_user',
            self::INSANE_USER => 'text_insane_user',
            self::VETERAN_USER => 'text_veteran_user',
            self::EXTREME_USER => 'text_extreme_user',
            self::ULTIMATE_USER => 'text_ultimate_user',
            self::NEXUS_MASTER => 'text_nexus_master',
            self::VIP => 'text_vip',
            self::RETIREE => 'text_retiree',
            self::UPLOADER => 'text_uploader',
            self::MODERATOR => 'text_moderators',
            self::ADMINISTRATOR => 'text_administrators',
            self::SYSOP => 'text_sysops',
            self::STAFFLEADER => 'text_staff_leader',
        };
    }

    /**
     * Minimum seed points required to reach this tier (0 if not applicable).
     *
     * VIP and above do not have seed-point thresholds — they are assigned manually.
     */
    public function minSeedPoints(): int
    {
        return match ($this) {
            self::PEASANT => 0,
            self::USER => 0,
            self::POWER_USER => 40000,
            self::ELITE_USER => 80000,
            self::CRAZY_USER => 150000,
            self::INSANE_USER => 250000,
            self::VETERAN_USER => 400000,
            self::EXTREME_USER => 600000,
            self::ULTIMATE_USER => 800000,
            self::NEXUS_MASTER => 1000000,
            self::VIP, self::RETIREE, self::UPLOADER,
            self::MODERATOR, self::ADMINISTRATOR,
            self::SYSOP, self::STAFFLEADER => 0,
        };
    }

    /**
     * Whether this tier is a staff role (Moderator or above).
     */
    public function isStaff(): bool
    {
        return $this->value >= self::MODERATOR->value;
    }

    /**
     * Whether this tier is VIP or above (but not staff).
     */
    public function isVipOrAbove(): bool
    {
        return $this->value >= self::VIP->value && $this->value < self::MODERATOR->value;
    }

    /**
     * Resolve a user class from an integer or numeric string, falling back to User.
     *
     * Handles values coming from the database (int), legacy string constants
     * (User::CLASS_*), or global UC_* defines.
     */
    public static function fromIntSafe(int|string $value): self
    {
        if (is_string($value)) {
            $value = (int) $value;
        }

        return self::tryFrom($value) ?? self::USER;
    }
}
