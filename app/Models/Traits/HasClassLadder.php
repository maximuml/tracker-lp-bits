<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\UserClass as UserClassEnum;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Locale;

/**
 * Encapsulates the NexusPHP user-class ladder: constants, metadata tables,
 * and helpers for rendering class names / labels.
 *
 * @property int|null $class
 */
trait HasClassLadder
{
    const CLASS_PEASANT = '0';

    const CLASS_USER = '1';

    const CLASS_POWER_USER = '2';

    const CLASS_ELITE_USER = '3';

    const CLASS_CRAZY_USER = '4';

    const CLASS_INSANE_USER = '5';

    const CLASS_VETERAN_USER = '6';

    const CLASS_EXTREME_USER = '7';

    const CLASS_ULTIMATE_USER = '8';

    const CLASS_NEXUS_MASTER = '9';

    const CLASS_VIP = '10';

    const CLASS_RETIREE = '11';

    const CLASS_UPLOADER = '12';

    const CLASS_MODERATOR = '13';

    const CLASS_ADMINISTRATOR = '14';

    const CLASS_SYSOP = '15';

    const CLASS_STAFF_LEADER = '16';

    /**
     * Convenience accessors to the UserClass enum for type-safe comparisons.
     */
    public static function classEnum(int|string $class): UserClassEnum
    {
        return UserClassEnum::fromIntSafe($class);
    }

    /** @var array<int|string, array<string, mixed>> */
    public static array $classes = [
        self::CLASS_PEASANT => ['text' => 'Peasant'],
        self::CLASS_USER => ['text' => 'User', 'min_seed_points' => 0],
        self::CLASS_POWER_USER => ['text' => 'Power User', 'min_seed_points' => 40000],
        self::CLASS_ELITE_USER => ['text' => 'Elite User', 'min_seed_points' => 80000],
        self::CLASS_CRAZY_USER => ['text' => 'Crazy User', 'min_seed_points' => 150000],
        self::CLASS_INSANE_USER => ['text' => 'Insane User', 'min_seed_points' => 250000],
        self::CLASS_VETERAN_USER => ['text' => 'Veteran User', 'min_seed_points' => 400000],
        self::CLASS_EXTREME_USER => ['text' => 'Extreme User', 'min_seed_points' => 600000],
        self::CLASS_ULTIMATE_USER => ['text' => 'Ultimate User', 'min_seed_points' => 800000],
        self::CLASS_NEXUS_MASTER => ['text' => 'Nexus Master', 'min_seed_points' => 1000000],
        self::CLASS_VIP => ['text' => 'VIP'],
        self::CLASS_RETIREE => ['text' => 'Retiree'],
        self::CLASS_UPLOADER => ['text' => 'Uploader'],
        self::CLASS_MODERATOR => ['text' => 'Moderator'],
        self::CLASS_ADMINISTRATOR => ['text' => 'Administrator'],
        self::CLASS_SYSOP => ['text' => 'Sysop'],
        self::CLASS_STAFF_LEADER => ['text' => 'Staff Leader'],
    ];

    public function getClassTextAttribute(): string
    {
        return self::getClassText((int) $this->class);
    }

    /**
     * @param  int|string  $class
     * @return string
     */
    public static function getClassText($class)
    {
        if (! is_numeric($class) || ! isset(self::$classes[$class])) {
            return '';
        }
        $classText = self::$classes[$class]['text'];
        if ($class >= self::CLASS_VIP) {
            $alias = Locale::trans('user.class_names.'.$class, [], null);
        } else {
            $alias = SiteConfig::current()->account->classAlias($class);
        }
        if (! empty($alias)) {
            $classText .= "({$alias})";
        }

        return $classText;
    }

    /**
     * @param  int|string  $min
     * @param  int|string  $max
     * @return array<int|string, string>
     */
    public static function listClass($min = self::CLASS_PEASANT, $max = self::CLASS_STAFF_LEADER): array
    {
        $result = [];
        foreach (self::$classes as $class => $info) {
            if ($class >= $min && $class <= $max) {
                $result[$class] = self::getClassText($class);
            }
        }

        return $result;
    }

    /**
     * @param  int|string  $class
     * @param  bool  $compact
     * @param  bool  $b_colored
     * @param  bool  $I18N
     * @return string
     */
    public static function getClassName($class, $compact = false, $b_colored = false, $I18N = false)
    {
        $class_name = self::$classes[$class]['text'] ?? '';
        if ($class >= self::CLASS_VIP && $I18N) {
            $class_name = Locale::trans("user.class_names.{$class}", [], null);
        }
        $class_name_color = self::$classes[$class]['text'] ?? '';
        if ($compact) {
            $class_name = str_replace(' ', '', $class_name);
        }
        if ($class_name && $b_colored) {
            return "<b class='".str_replace(' ', '', $class_name_color)."_Name'>".$class_name.'</b>';
        }

        return $class_name;
    }

    /**
     * @param  int|string  $class
     * @return int|float|false
     */
    public static function getMinSeedPoints($class)
    {
        $setting = SiteConfig::current()->account->classMinSeedPoints($class);
        if (is_numeric($setting)) {
            return $setting;
        }

        return self::$classes[$class]['min_seed_points'] ?? false;
    }

    /** @return int|string */
    public static function getAccessAdminClassMin()
    {
        return SiteConfig::current()->system->accessAdminClassMin() ?: User::CLASS_ADMINISTRATOR;
    }
}
