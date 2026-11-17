<?php

namespace Tests\Unit\Support;

use App\Support\UserClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
class UserClassTest extends TestCase
{
    /**
     * The complete legacy ladder: every `UC_*` tier and the exact
     * lang-array key the old `switch` blocks produced. Pins the staff
     * tiers' deliberately pluralised keys.
     *
     * @return array<string, array{int, string}>
     */
    public static function tierProvider(): array
    {
        return [
            'peasant' => [UserClass::PEASANT, 'text_peasant'],
            'user' => [UserClass::USER, 'text_user'],
            'power user' => [UserClass::POWER_USER, 'text_power_user'],
            'elite user' => [UserClass::ELITE_USER, 'text_elite_user'],
            'crazy user' => [UserClass::CRAZY_USER, 'text_crazy_user'],
            'insane user' => [UserClass::INSANE_USER, 'text_insane_user'],
            'veteran user' => [UserClass::VETERAN_USER, 'text_veteran_user'],
            'extreme user' => [UserClass::EXTREME_USER, 'text_extreme_user'],
            'ultimate user' => [UserClass::ULTIMATE_USER, 'text_ultimate_user'],
            'nexus master' => [UserClass::NEXUS_MASTER, 'text_nexus_master'],
            'vip' => [UserClass::VIP, 'text_vip'],
            'retiree' => [UserClass::RETIREE, 'text_retiree'],
            'uploader' => [UserClass::UPLOADER, 'text_uploader'],
            'moderator (plural key)' => [UserClass::MODERATOR, 'text_moderators'],
            'administrator (plural key)' => [UserClass::ADMINISTRATOR, 'text_administrators'],
            'sysop (plural key)' => [UserClass::SYSOP, 'text_sysops'],
            'staff leader' => [UserClass::STAFFLEADER, 'text_staff_leader'],
        ];
    }

    #[DataProvider('tierProvider')]
    public function test_lang_key_maps_each_tier_to_its_legacy_key(int $class, string $expected): void
    {
        $this->assertSame($expected, UserClass::langKey($class));
    }

    public function test_constant_values_match_legacy_uc_ladder(): void
    {
        // Pinned against include/constants.php — the integers are part
        // of the contract (stored in the DB, compared across the app).
        $this->assertSame(0, UserClass::PEASANT);
        $this->assertSame(1, UserClass::USER);
        $this->assertSame(10, UserClass::VIP);
        $this->assertSame(11, UserClass::RETIREE);
        $this->assertSame(12, UserClass::UPLOADER);
        $this->assertSame(16, UserClass::STAFFLEADER);
    }

    public function test_lang_key_returns_null_for_unknown_tier(): void
    {
        // Legacy switch had no default, leaving the class name empty;
        // callers fall back to '' via `?? ''`.
        $this->assertNull(UserClass::langKey(17));
        $this->assertNull(UserClass::langKey(-1));
        $this->assertNull(UserClass::langKey(999));
    }

    // ---------- badgeSlug() ----------

    public function test_badge_slug_returns_compact_lowercase_for_each_class(): void
    {
        $expected = [
            'Staff Leader' => 'staffleader',
            'SysOp' => 'sysop',
            'Administrator' => 'administrator',
            'Moderator' => 'moderator',
            'Forum Moderator' => 'forummoderator',
            'Uploader' => 'uploader',
            'Retiree' => 'retiree',
            'VIP' => 'vip',
            'Nexus Master' => 'nexusmaster',
            'Ultimate User' => 'ultimateuser',
            'Extreme User' => 'extremeuser',
            'Veteran User' => 'veteranuser',
            'Insane User' => 'insaneuser',
            'Crazy User' => 'crazyuser',
            'Elite User' => 'eliteuser',
            'Power User' => 'poweruser',
            'User' => 'user',
            'Peasant' => 'peasant',
        ];

        foreach ($expected as $className => $slug) {
            $this->assertSame($slug, UserClass::badgeSlug($className), "Class '$className' should slug to '$slug'");
        }
    }

    public function test_badge_slug_int_tier_resolves_via_enum_label(): void
    {
        $this->assertSame('poweruser', UserClass::badgeSlug(UserClass::POWER_USER));
        $this->assertSame('peasant', UserClass::badgeSlug(UserClass::PEASANT));
        $this->assertSame('sysop', UserClass::badgeSlug(UserClass::SYSOP));
    }

    public function test_badge_slug_null_and_empty_return_banned(): void
    {
        $this->assertSame('banned', UserClass::badgeSlug(null));
        $this->assertSame('banned', UserClass::badgeSlug(''));
    }

    public function test_badge_slug_unknown_int_tier_returns_banned(): void
    {
        $this->assertSame('banned', UserClass::badgeSlug(17));
        $this->assertSame('banned', UserClass::badgeSlug(999));
    }

    public function test_badge_slug_strips_alias_suffix(): void
    {
        // Legacy `get_user_class_name` can return "Power User(Custom Alias)"
        // when $options['with_alias'] is set. The parenthesized suffix
        // must be stripped before slugging.
        $this->assertSame('poweruser', UserClass::badgeSlug('Power User(Pro)'));
        $this->assertSame('vip', UserClass::badgeSlug('VIP(Lifetime)'));
    }

    public function test_badge_slug_alias_only_parenthesis_returns_banned(): void
    {
        // Edge case: string starts with "(" → strstr returns "" → banned
        $this->assertSame('banned', UserClass::badgeSlug('(OnlyAlias)'));
    }
}
