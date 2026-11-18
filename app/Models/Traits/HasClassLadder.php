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
    /** @var array<int, array{text: string, min_seed_points?: int}>|null */
    private static ?array $ladderMap = null;

    /**
     * Tier metadata map, derived from {@see UserClassEnum} — the single
     * source for labels and seed-point thresholds. `min_seed_points` is
     * present only on the auto-promotion rungs plus User (the legacy map
     * carried `0` there and omitted the key elsewhere).
     *
     * @return array<int, array{text: string, min_seed_points?: int}>
     */
    public static function classes(): array
    {
        if (self::$ladderMap === null) {
            self::$ladderMap = [];
            foreach (UserClassEnum::cases() as $case) {
                $entry = ['text' => $case->label()];
                if ($case === UserClassEnum::USER || $case->minSeedPoints() > 0) {
                    $entry['min_seed_points'] = $case->minSeedPoints();
                }
                self::$ladderMap[$case->value] = $entry;
            }
        }

        return self::$ladderMap;
    }

    /**
     * Drop the memoized ladder map (mutable-static ratchet requires a
     * reset path; also lets tests re-derive after locale/config changes).
     */
    public static function resetLadder(): void
    {
        self::$ladderMap = null;
    }

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
        if (! is_numeric($class) || ! isset(self::classes()[$class])) {
            return '';
        }
        $classText = self::classes()[$class]['text'];
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
        foreach (self::classes() as $class => $info) {
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
        $class_name = self::classes()[$class]['text'] ?? '';
        if ($class >= UserClassEnum::VIP->value && $I18N) {
            $class_name = Locale::trans("user.class_names.{$class}", [], null);
        }
        $class_name_color = self::classes()[$class]['text'] ?? '';
        if ($compact) {
            $class_name = str_replace(' ', '', $class_name);
        }
        if ($class_name && $b_colored) {
            return trim(view('components.support.class-name-b', [
                'cls' => str_replace(' ', '', $class_name_color),
                'inner' => $class_name,
            ])->render());
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

        return self::classes()[$class]['min_seed_points'] ?? false;
    }

    /** @return int|string */
    public static function getAccessAdminClassMin()
    {
        return SiteConfig::current()->system->accessAdminClassMin() ?: UserClassEnum::ADMINISTRATOR->value;
    }
}
