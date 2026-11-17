<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserClass as UserClassEnum;
use App\Models\Setting;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
use App\Support\State\UserClassState;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Stateless mapping helpers for the legacy user-class ladder.
 *
 * Phase 5 of the legacy migration.
 *
 * `\App\Support\UserClass::name()` in `include/functions.php` carried two
 * identical 17-case `switch` blocks: one to pick the display label in
 * the viewer's language and one to pick the English label used to build
 * the `<b class="..._Name">` colour class. Both switches mapped a
 * `UC_*` tier to the *same* lang-array key, so the only pure logic was
 * that tier → key mapping. It is extracted here as {@see langKey()} and
 * both legacy switches collapse into a single lookup.
 *
 * Themed class badges (replacing the legacy `pic/*.gif` icons) resolve
 * through {@see badgeSlug()}: the lowercase compact English tier name
 * used to pick the `--nxm-name-*` design token.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Validators} and {@see Ratio}.
 *
 * The `UC_*` integer values are pinned as constants so this helper does
 * not have to load the legacy bootstrap (`include/constants.php`). If a
 * new tier is ever added to the ladder, both this map and the legacy
 * constants must grow together.
 *
 * Every entry is pinned by a unit test in
 * `tests/Unit/Support/UserClassTest.php`.
 */
final class UserClass
{
    public const PEASANT = UserClassEnum::PEASANT->value;

    public const USER = UserClassEnum::USER->value;

    public const POWER_USER = UserClassEnum::POWER_USER->value;

    public const ELITE_USER = UserClassEnum::ELITE_USER->value;

    public const CRAZY_USER = UserClassEnum::CRAZY_USER->value;

    public const INSANE_USER = UserClassEnum::INSANE_USER->value;

    public const VETERAN_USER = UserClassEnum::VETERAN_USER->value;

    public const EXTREME_USER = UserClassEnum::EXTREME_USER->value;

    public const ULTIMATE_USER = UserClassEnum::ULTIMATE_USER->value;

    public const NEXUS_MASTER = UserClassEnum::NEXUS_MASTER->value;

    public const VIP = UserClassEnum::VIP->value;

    public const RETIREE = UserClassEnum::RETIREE->value;

    public static function resetState(): void
    {
        UserClassState::instance()->reset();
    }

    public const UPLOADER = UserClassEnum::UPLOADER->value;

    public const MODERATOR = UserClassEnum::MODERATOR->value;

    public const ADMINISTRATOR = UserClassEnum::ADMINISTRATOR->value;

    public const SYSOP = UserClassEnum::SYSOP->value;

    public const STAFFLEADER = UserClassEnum::STAFFLEADER->value;

    /**
     * Resolve the lang-array key for a user-class tier, or `null` for
     * an unknown tier. Mirrors the legacy `switch` default of leaving
     * the class name empty: callers fall back to `'' ` when the key is
     * absent (e.g. `$lang[UserClass::langKey($c)] ?? ''`).
     */
    public static function langKey(int $class): ?string
    {
        $enum = UserClassEnum::tryFrom($class);

        return $enum?->langKey();
    }

    /**
     * Slug used by `<x-support.class-badge>` to pick the tier's
     * `--nxm-name-*` design token: the lowercase compact English tier
     * name (`Power User` -> `poweruser`), or `banned` for null /
     * unrecognised values.
     *
     * Accepts the integer `UC_*` tier, or an English class-name string
     * (the shape `UserClass::name($class, false, false, false)` returns);
     * an `(Alias)` suffix is stripped before slugging -- matching the
     * legacy `strstr($className, '(', true)` behaviour.
     */
    public static function badgeSlug(int|string|null $class): string
    {
        if ($class === null || $class === '') {
            return 'banned';
        }

        if (is_int($class) || ctype_digit($class)) {
            $case = UserClassEnum::tryFrom((int) $class);

            return $case === null
                ? 'banned'
                : strtolower(str_replace(' ', '', $case->label()));
        }

        if (str_contains($class, '(')) {
            $class = strstr($class, '(', true);
            if ($class === false || $class === '') {
                return 'banned';
            }
        }

        $slug = strtolower(str_replace(' ', '', $class));

        return $slug === '' ? 'banned' : $slug;
    }

    /**
     * Legacy user-class name formatter.
     *
     * Temporary Phase 5 shim that mirrors `\App\Support\UserClass::name()` from
     * `include/functions.php`. In Laravel context it delegates to
     * `User::getClassName()`; in legacy context it loads the language
     * packs, applies class aliases, and optionally wraps the name in
     * the coloured `<b class="..._Name">` tag.
     */
    /**
     * @param  array<string, mixed>  $options
     */
    public static function name(
        int|string $class,
        bool $compact = false,
        bool $b_colored = false,
        bool $I18N = false,
        array $options = [],
    ): SafeHtml {
        if (! RuntimeContext::instance()->isLegacy()) {
            return SafeHtml::fromTrustedHtml(User::getClassName($class, $compact, $b_colored, $I18N));
        }

        if (UserClassState::instance()->settingAccount === null) {
            UserClassState::instance()->settingAccount = SiteConfig::current()->account->toArray();
        }

        $langKey = self::langKey((int) $class);
        $className = $langKey !== null ? (string) __(
            'functions.'.$langKey,
            [],
            $I18N ? null : 'en'
        ) : '';

        if (isset($options['with_alias']) && $options['with_alias'] && (int) $class < self::VIP && isset(UserClassState::instance()->settingAccount["{$class}_alias"])) {
            $alias = trim(UserClassState::instance()->settingAccount["{$class}_alias"]);
            if ($alias !== '') {
                $className = sprintf('%s(%s)', $className, $alias);
            }
        }

        $classNameColor = $langKey !== null ? (string) __('functions.'.$langKey, [], 'en') : '';
        $className = $compact ? str_replace(' ', '', $className) : $className;

        if (isset($options['uid'], $options['with_role'])) {
            $className = implode('&nbsp;|&nbsp;', [$className]);
        }

        if ($className && $b_colored) {
            $className = trim(view('components.support.class-name-b', [
                'cls' => str_replace(' ', '', $classNameColor),
                'inner' => $className,
            ])->render());
        }

        return SafeHtml::fromTrustedHtml($className);
    }

    /**
     * Build a `<select>` of user-class tiers.
     *
     * Mirrors `classlist()`.
     *
     * @param  array<string, string>  $labels  Current language labels; must contain
     *                                         `select_an_user_class` when `$includeNoClass` is true.
     */
    public static function classSelect(
        string $selectName,
        int $maxClass,
        int|string $selected,
        int $minClass = 0,
        bool $includeNoClass = false,
        bool $disabled = false,
        array $labels = [],
        ?string $ariaLabel = null,
    ): Htmlable {
        $options = '';

        if ($includeNoClass) {
            $options .= trim(view('components.support.tag.option', [
                'value' => Setting::PERMISSION_NO_CLASS,
                'label' => $labels['select_an_user_class'] ?? '---',
                'selected' => false,
            ])->render());
        }

        for ($i = $minClass; $i <= $maxClass; $i++) {
            $options .= trim(view('components.support.tag.option', [
                'value' => $i,
                'label' => self::name($i, false, false, true),
                'selected' => (int) $selected === $i,
            ])->render())."\n";
        }

        return view('components.support.class-select', [
            'name' => $selectName,
            'disabled' => $disabled,
            'options' => SafeHtml::fromTrustedHtml($options),
            'ariaLabel' => $ariaLabel,
        ]);
    }

    /**
     * Build a class select with language labels from the request context.
     *
     * Backs the legacy `classlist()` helper in views.
     */
    public static function classSelectWithContext(
        string $selectName,
        int $maxClass,
        int|string $selected,
        int $minClass = 0,
        bool $includeNoClass = false,
        bool $disabled = false,
        ?string $ariaLabel = null,
    ): Htmlable {

        return self::classSelect(
            $selectName,
            $maxClass,
            $selected,
            $minClass,
            $includeNoClass,
            $disabled,
            ['select_an_user_class' => __('functions.select_an_user_class')],
            $ariaLabel,
        );
    }
}
