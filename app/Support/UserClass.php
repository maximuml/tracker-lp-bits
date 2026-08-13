<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Stateless mapping helpers for the legacy user-class ladder.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 *
 * `\App\Support\UserClass::name()` in `include/functions.php` carried two
 * identical 17-case `switch` blocks: one to pick the display label in
 * the viewer's language and one to pick the English label used to build
 * the `<b class="..._Name">` colour class. Both switches mapped a
 * `UC_*` tier to the *same* lang-array key, so the only pure logic was
 * that tier → key mapping. It is extracted here as {@see langKey()} and
 * both legacy switches collapse into a single lookup.
 *
 * The class-icon resolution from `get_user_class_image()` also lives
 * here as {@see imagePath()} / {@see allImagePaths()}: a pure map from
 * the *English* class-name string to its `pic/*.gif` icon path.
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
    public const PEASANT = 0;

    public const USER = 1;

    public const POWER_USER = 2;

    public const ELITE_USER = 3;

    public const CRAZY_USER = 4;

    public const INSANE_USER = 5;

    public const VETERAN_USER = 6;

    public const EXTREME_USER = 7;

    public const ULTIMATE_USER = 8;

    public const NEXUS_MASTER = 9;

    public const VIP = 10;

    public const RETIREE = 11;

    public const UPLOADER = 12;

    public const MODERATOR = 13;

    public const ADMINISTRATOR = 14;

    public const SYSOP = 15;

    public const STAFFLEADER = 16;

    /**
     * Lang-array key for each user-class tier.
     *
     * Note the deliberately pluralised keys for the staff tiers
     * (`text_moderators`, `text_administrators`, `text_sysops`) —
     * those match the legacy lang files exactly and must not be
     * "corrected" to singular forms.
     *
     * @var array<int, string>
     */
    private const LANG_KEYS = [
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
    ];

    /**
     * Resolve the lang-array key for a user-class tier, or `null` for
     * an unknown tier. Mirrors the legacy `switch` default of leaving
     * the class name empty: callers fall back to `'' ` when the key is
     * absent (e.g. `$lang[UserClass::langKey($c)] ?? ''`).
     */
    public static function langKey(int $class): ?string
    {
        return self::LANG_KEYS[$class] ?? null;
    }

    /**
     * Map of English class names → image paths. Matches the legacy
     * `$UC` array in `get_user_class_image()` exactly, including the
     * `"pic/"` prefix convention — these paths are relative to the
     * web root (`public/`).
     *
     * @var array<string, string>
     */
    private const IMAGE_MAP = [
        'Staff Leader' => 'pic/staffleader.gif',
        'SysOp' => 'pic/sysop.gif',
        'Administrator' => 'pic/administrator.gif',
        'Moderator' => 'pic/moderator.gif',
        'Forum Moderator' => 'pic/forummoderator.gif',
        'Uploader' => 'pic/uploader.gif',
        'Retiree' => 'pic/retiree.gif',
        'VIP' => 'pic/vip.gif',
        'Nexus Master' => 'pic/nexus.gif',
        'Ultimate User' => 'pic/ultimate.gif',
        'Extreme User' => 'pic/extreme.gif',
        'Veteran User' => 'pic/veteran.gif',
        'Insane User' => 'pic/insane.gif',
        'Crazy User' => 'pic/crazy.gif',
        'Elite User' => 'pic/elite.gif',
        'Power User' => 'pic/power.gif',
        'User' => 'pic/user.gif',
        'Peasant' => 'pic/peasant.gif',
    ];

    /** Image path for unrecognised / null class values. */
    private const BANNED_IMAGE = 'pic/banned.gif';

    /**
     * Resolve the class-icon image path for a given user class.
     *
     * Accepts either the integer `UC_*` tier or the *English* class-name
     * string (the same shape that `\App\Support\UserClass::name($class,
     * false, false, false)` returns). Integer tiers are resolved to their
     * English name first so callers like `get_user_class_image()` keep
     * working without extra plumbing.
     *
     * If the name contains parentheses (e.g. `"Power User(Custom Alias)"`)
     * the alias suffix is stripped before lookup — matching the legacy
     * `strstr($className, '(', true)` behaviour.
     *
     * Returns the relative image path (e.g. `"pic/power.gif"`) or
     * `"pic/banned.gif"` when the class is null / unrecognised.
     */
    public static function imagePath(int|string|null $class): string
    {
        if ($class === null || $class === '') {
            return self::BANNED_IMAGE;
        }

        if (is_int($class) || ctype_digit($class)) {
            $className = self::name((int) $class, false, false, false);
        } else {
            $className = $class;
        }

        // Strip alias suffix: "Power User(Custom)" → "Power User"
        if (str_contains($className, '(')) {
            $className = strstr($className, '(', true);
            if ($className === false || $className === '') {
                return self::BANNED_IMAGE;
            }
        }

        return self::IMAGE_MAP[$className] ?? self::BANNED_IMAGE;
    }

    /**
     * Expose the full image map for callers that need to iterate
     * (e.g. admin panels listing all class icons).
     *
     * @return array<string, string>
     */
    public static function allImagePaths(): array
    {
        return self::IMAGE_MAP;
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
     * @param  int|string  $class
     * @param  array<string, mixed>  $options
     */
    public static function name(
        int|string $class,
        bool $compact = false,
        bool $b_colored = false,
        bool $I18N = false,
        array $options = [],
    ): string {
        if (! (defined('IN_NEXUS') && IN_NEXUS)) {
            return User::getClassName($class, $compact, $b_colored, $I18N);
        }

        static $enLangFunctions = null;
        static $currentLangFunctions = null;
        static $settingAccount = null;
        $lang_functions = [];

        if ($enLangFunctions === null) {
            require \App\Support\Locale::scriptFilePath((string) 'functions.php', (bool) false, (string) 'en');
            $enLangFunctions = $lang_functions;
        }

        if ($settingAccount === null) {
            $settingAccount = \App\Support\Config\SiteConfig::current()->account->toArray();
        }

        if ($I18N) {
            if ($currentLangFunctions === null) {
                require \App\Support\Locale::scriptFilePath((string) 'functions.php', (bool) false, (string) "");
                $currentLangFunctions = $lang_functions;
            }
            $thisLangFunctions = $currentLangFunctions;
        } else {
            $thisLangFunctions = $enLangFunctions;
        }

        $langKey = self::langKey((int) $class);
        $className = $langKey !== null ? (string) ($thisLangFunctions[$langKey] ?? '') : '';

        if (isset($options['with_alias']) && $options['with_alias'] && (int) $class < self::VIP && isset($settingAccount["{$class}_alias"])) {
            $alias = trim($settingAccount["{$class}_alias"]);
            if ($alias !== '') {
                $className = sprintf('%s(%s)', $className, $alias);
            }
        }

        $classNameColor = $langKey !== null ? (string) ($enLangFunctions[$langKey] ?? '') : '';
        $className = $compact ? str_replace(' ', '', $className) : $className;

        if (isset($options['uid'], $options['with_role'])) {
            $className = implode('&nbsp;|&nbsp;', Hooks::applyFilter('user_class_name', [$className], $options['uid']));
        }

        if ($className && $b_colored) {
            $className = "<b class='".str_replace(' ', '', $classNameColor)."_Name'>".$className.'</b>';
        }

        return $className;
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
    ): string {
        $disabledText = $disabled ? ' disabled = "disabled"' : '';
        $list = "<select name=\"" . $selectName . "\"" . $disabledText . ">";

        if ($includeNoClass) {
            $list .= sprintf(
                '<option value="%s">%s</option>',
                Setting::PERMISSION_NO_CLASS,
                $labels['select_an_user_class'] ?? '---'
            );
        }

        for ($i = $minClass; $i <= $maxClass; $i++) {
            $selectedAttr = (int) $selected === $i ? ' selected="selected"' : '';
            $list .= "<option value=\"" . $i . "\"" . $selectedAttr . ">" . self::name($i, false, false, true) . "</option>\n";
        }

        $list .= "</select>";
        return $list;
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
    ): string {
        $lang = \App\Support\SupportContext::getLangFunctions();

        return self::classSelect(
            $selectName,
            $maxClass,
            $selected,
            $minClass,
            $includeNoClass,
            $disabled,
            ['select_an_user_class' => $lang['select_an_user_class'] ?? '---'],
        );
    }
}
