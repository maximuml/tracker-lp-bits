<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\LanguageRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for LanguageRepository.
 *
 * Covers getUserFolder(), getFolderForId(), getIdFromFolder(),
 * getLanguageList(), getGuestId() and getUserLocale().
 */
final class LanguageRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private LanguageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('language')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        Cache::flush();

        $this->repository = new LanguageRepository;
    }

    public function test_get_user_folder_returns_en_when_user_not_found(): void
    {
        $this->assertSame('en', $this->repository->getUserFolder(99999));
    }

    public function test_get_user_folder_returns_folder_for_user_language(): void
    {
        $langId = (int) DB::table('language')->insertGetId([
            'lang_name' => 'English',
            'site_lang' => 1,
            'site_lang_folder' => 'en',
            'flagpic' => 'en.gif',
        ]);
        /** @var User $user */
        $user = User::factory()->create(['lang' => $langId]);

        $this->assertSame('en', $this->repository->getUserFolder($user->id));
    }

    public function test_get_folder_for_id_returns_default_when_not_found(): void
    {
        $this->assertSame('en', $this->repository->getFolderForId(99999));
    }

    public function test_get_folder_for_id_returns_custom_default_when_not_found(): void
    {
        $this->assertSame('custom', $this->repository->getFolderForId(99999, 'custom'));
    }

    public function test_get_folder_for_id_returns_folder_when_found(): void
    {
        $langId = (int) DB::table('language')->insertGetId([
            'lang_name' => 'Chinese',
            'site_lang' => 1,
            'site_lang_folder' => 'zh-CN',
            'flagpic' => 'cn.gif',
        ]);

        $this->assertSame('zh-CN', $this->repository->getFolderForId($langId));
    }

    public function test_get_folder_for_id_ignores_non_site_languages(): void
    {
        $langId = (int) DB::table('language')->insertGetId([
            'lang_name' => 'Other',
            'site_lang' => 0,
            'site_lang_folder' => 'other',
            'flagpic' => 'o.gif',
        ]);

        $this->assertSame('en', $this->repository->getFolderForId($langId));
    }

    public function test_get_id_from_folder_returns_zero_when_not_found(): void
    {
        $this->assertSame(0, $this->repository->getIdFromFolder('nonexistent'));
    }

    public function test_get_id_from_folder_returns_id_when_found(): void
    {
        $langId = (int) DB::table('language')->insertGetId([
            'lang_name' => 'English',
            'site_lang' => 1,
            'site_lang_folder' => 'en',
            'flagpic' => 'en.gif',
        ]);

        $this->assertSame($langId, $this->repository->getIdFromFolder('en'));
    }

    public function test_get_language_list_returns_matching_languages(): void
    {
        DB::table('language')->insert([
            ['lang_name' => 'English', 'site_lang' => 1, 'sub_lang' => 1, 'rule_lang' => 0, 'site_lang_folder' => 'en', 'flagpic' => 'en.gif'],
            ['lang_name' => 'Chinese', 'site_lang' => 1, 'sub_lang' => 0, 'rule_lang' => 0, 'site_lang_folder' => 'zh-CN', 'flagpic' => 'cn.gif'],
        ]);

        Cache::flush();
        $result = $this->repository->getLanguageList('site_lang');

        $this->assertCount(2, $result);
        $folders = array_column($result, 'site_lang_folder');
        $this->assertContains('en', $folders);
        $this->assertContains('zh-CN', $folders);
    }

    public function test_get_language_list_returns_empty_when_no_match(): void
    {
        DB::table('language')->insert([
            ['lang_name' => 'English', 'site_lang' => 0, 'sub_lang' => 0, 'rule_lang' => 0, 'site_lang_folder' => 'en', 'flagpic' => 'en.gif'],
        ]);

        Cache::flush();
        $result = $this->repository->getLanguageList('site_lang');

        $this->assertSame([], $result);
    }

    public function test_get_guest_id_returns_default_when_not_found(): void
    {
        $this->assertSame(6, $this->repository->getGuestId('nonexistent'));
    }

    public function test_get_guest_id_returns_id_when_found(): void
    {
        $langId = (int) DB::table('language')->insertGetId([
            'lang_name' => 'English',
            'site_lang' => 1,
            'site_lang_folder' => 'en',
            'flagpic' => 'en.gif',
        ]);

        $this->assertSame($langId, $this->repository->getGuestId('en'));
    }

    public function test_get_user_locale_returns_en_when_user_not_found(): void
    {
        $this->assertSame('en', $this->repository->getUserLocale(99999));
    }

    public function test_get_user_locale_returns_mapped_locale(): void
    {
        $langId = (int) DB::table('language')->insertGetId([
            'lang_name' => 'English',
            'site_lang' => 1,
            'site_lang_folder' => 'en',
            'flagpic' => 'en.gif',
        ]);
        /** @var User $user */
        $user = User::factory()->create(['lang' => $langId]);

        $this->assertSame('en', $this->repository->getUserLocale($user->id));
    }
}
