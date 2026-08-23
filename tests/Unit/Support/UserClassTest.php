<?php

namespace Tests\Unit\Support;

use App\Support\UserClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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

    // ---------- imagePath() ----------

    public function test_image_path_returns_correct_gif_for_each_class(): void
    {
        $expected = [
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

        foreach ($expected as $className => $path) {
            $this->assertSame($path, UserClass::imagePath($className), "Class '$className' should map to '$path'");
        }
    }

    public function test_image_path_null_returns_banned(): void
    {
        $this->assertSame('pic/banned.gif', UserClass::imagePath(null));
    }

    public function test_image_path_empty_string_returns_banned(): void
    {
        $this->assertSame('pic/banned.gif', UserClass::imagePath(''));
    }

    public function test_image_path_unknown_class_returns_banned(): void
    {
        $this->assertSame('pic/banned.gif', UserClass::imagePath('NonExistent'));
    }

    public function test_image_path_strips_alias_suffix(): void
    {
        // Legacy `get_user_class_name` can return "Power User(Custom Alias)"
        // when $options['with_alias'] is set. The parenthesized suffix
        // must be stripped before image lookup.
        $this->assertSame('pic/power.gif', UserClass::imagePath('Power User(Pro)'));
        $this->assertSame('pic/vip.gif', UserClass::imagePath('VIP(Lifetime)'));
    }

    public function test_image_path_alias_only_parenthesis_returns_banned(): void
    {
        // Edge case: string starts with "(" → strstr returns "" → banned
        $this->assertSame('pic/banned.gif', UserClass::imagePath('(OnlyAlias)'));
    }

    // ---------- allImagePaths() ----------

    public function test_all_image_paths_returns_18_entries(): void
    {
        $this->assertCount(18, UserClass::allImagePaths());
    }

    public function test_all_image_paths_keys_are_class_names(): void
    {
        $map = UserClass::allImagePaths();
        $this->assertArrayHasKey('User', $map);
        $this->assertArrayHasKey('Staff Leader', $map);
    }

    public function test_all_image_paths_values_start_with_pic(): void
    {
        foreach (UserClass::allImagePaths() as $name => $path) {
            $this->assertStringStartsWith('pic/', $path, "Image for '$name' should start with 'pic/'");
        }
    }

    // ---------- name() ----------

    public function test_name_returns_english_class_text(): void
    {
        $this->assertSame('User', UserClass::name(1));
    }

    public function test_name_colored_wraps_in_bold_tag(): void
    {
        $this->assertSame("<b class='User_Name'>User</b>", UserClass::name(1, false, true, false));
    }

    public function test_name_compact_removes_spaces(): void
    {
        $this->assertSame('PowerUser', UserClass::name(2, true, false, false));
    }
}
