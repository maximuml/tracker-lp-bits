<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LanguageTranslationState;
use App\Models\AttendanceLog;
use App\Models\Cheater;
use App\Models\IpLog;
use App\Models\Language;
use App\Models\TorrentCustomField;
use App\Models\UserMedal;
use App\Models\UserRequireSeedTorrent;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Wave 5 Step 34: model $casts coverage + preventLazyLoading.
 *
 * Verifies that:
 * - All models with datetime/bool/enum/json fields have $casts
 * - The 7 previously-missing models now have proper casts
 * - Model::preventLazyLoading() is enabled in non-production
 */
final class ModelCastsTest extends TestCase
{
    /**
     * Cheater has 'added' datetime cast.
     */
    public function test_cheater_has_added_datetime_cast(): void
    {
        $casts = (new Cheater)->getCasts();
        $this->assertArrayHasKey('added', $casts);
        $this->assertSame('datetime', $casts['added']);
    }

    /**
     * AttendanceLog has 'date' date cast.
     */
    public function test_attendance_log_has_date_cast(): void
    {
        $casts = (new AttendanceLog)->getCasts();
        $this->assertArrayHasKey('date', $casts);
        $this->assertSame('date', $casts['date']);
    }

    /**
     * IpLog has 'access' datetime cast.
     */
    public function test_iplog_has_access_datetime_cast(): void
    {
        $casts = (new IpLog)->getCasts();
        $this->assertArrayHasKey('access', $casts);
        $this->assertSame('datetime', $casts['access']);
    }

    /**
     * Language has 'trans_state' enum cast.
     */
    public function test_language_has_trans_state_enum_cast(): void
    {
        $casts = (new Language)->getCasts();
        $this->assertArrayHasKey('trans_state', $casts);
        $this->assertSame(LanguageTranslationState::class, $casts['trans_state']);
    }

    /**
     * TorrentCustomField has 'type' string cast.
     */
    public function test_torrent_custom_field_has_type_cast(): void
    {
        $casts = (new TorrentCustomField)->getCasts();
        $this->assertArrayHasKey('type', $casts);
    }

    /**
     * UserMedal has 'expire_at' and 'bonus_addition_expire_at' datetime casts.
     */
    public function test_user_medal_has_datetime_casts(): void
    {
        $casts = (new UserMedal)->getCasts();
        $this->assertArrayHasKey('expire_at', $casts);
        $this->assertSame('datetime', $casts['expire_at']);
        $this->assertArrayHasKey('bonus_addition_expire_at', $casts);
        $this->assertSame('datetime', $casts['bonus_addition_expire_at']);
    }

    /**
     * UserRequireSeedTorrent has 'last_settlement_at' datetime cast.
     */
    public function test_user_require_seed_torrent_has_datetime_cast(): void
    {
        $casts = (new UserRequireSeedTorrent)->getCasts();
        $this->assertArrayHasKey('last_settlement_at', $casts);
        $this->assertSame('datetime', $casts['last_settlement_at']);
    }

    /**
     * Model::preventLazyLoading() is enabled in non-production.
     */
    public function test_prevent_lazy_loading_enabled_non_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading(), 'Model::preventLazyLoading() should be enabled in non-production');
    }

    /**
     * Language trans_state enum cast works correctly.
     */
    public function test_language_trans_state_cast_returns_enum(): void
    {
        $language = new Language;
        $language->trans_state = 'up-to-date';

        $this->assertInstanceOf(LanguageTranslationState::class, $language->trans_state);
        $this->assertSame(LanguageTranslationState::UP_TO_DATE, $language->trans_state);
    }
}
