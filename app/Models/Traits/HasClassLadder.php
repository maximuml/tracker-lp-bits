<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\UserClass as UserClassEnum;
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
    /**
     * Convenience accessors to the UserClass enum for type-safe comparisons.
     */
    public static function classEnum(int|string $class): UserClassEnum
    {
        return UserClassEnum::fromIntSafe($class);
    }

    /** @var array<int|string, array<string, mixed>> */
    public static array $classes = [
        UserClassEnum::PEASANT->value => ['text' => 'Peasant'],
        UserClassEnum::USER->value => ['text' => 'User', 'min_seed_points' => 0],
        UserClassEnum::POWER_USER->value => ['text' => 'Power User', 'min_seed_points' => 40000],
        UserClassEnum::ELITE_USER->value => ['text' => 'Elite User', 'min_seed_points' => 80000],
        UserClassEnum::CRAZY_USER->value => ['text' => 'Crazy User', 'min_seed_points' => 150000],
        UserClassEnum::INSANE_USER->value => ['text' => 'Insane User', 'min_seed_points' => 250000],
        UserClassEnum::VETERAN_USER->value => ['text' => 'Veteran User', 'min_seed_points' => 400000],
        UserClassEnum::EXTREME_USER->value => ['text' => 'Extreme User', 'min_seed_points' => 600000],
        UserClassEnum::ULTIMATE_USER->value => ['text' => 'Ultimate User', 'min_seed_points' => 800000],
        UserClassEnum::NEXUS_MASTER->value => ['text' => 'Nexus Master', 'min_seed_points' => 1000000],
        UserClassEnum::VIP->value => ['text' => 'VIP'],
        UserClassEnum::RETIREE->value => ['text' => 'Retiree'],
        UserClassEnum::UPLOADER->value => ['text' => 'Uploader'],
        UserClassEnum::MODERATOR->value => ['text' => 'Moderator'],
        UserClassEnum::ADMINISTRATOR->value => ['text' => 'Administrator'],
        UserClassEnum::SYSOP->value => ['text' => 'Sysop'],
        UserClassEnum::STAFFLEADER->value => ['text' => 'Staff Leader'],
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
        if ($class >= UserClassEnum::VIP->value) {
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
    public static function listClass($min = UserClassEnum::PEASANT->value, $max = UserClassEnum::STAFFLEADER->value): array
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
        if ($class >= UserClassEnum::VIP->value && $I18N) {
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
        return SiteConfig::current()->system->accessAdminClassMin() ?: UserClassEnum::ADMINISTRATOR->value;
    }
}
