<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\InfoRepository;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for InfoRepository.
 *
 * Covers resolveRuleLangId(), faqCategories(), rules(), faqManageData(),
 * reorderFaq(), updateFaq(), deleteFaq(), getFaqById(),
 * getFaqCategoriesByLang(), getLanguageName(), getFaqMaxOrderAndLinkId(),
 * insertFaq(), aboutNexus(), donationPageData(), getUserHistoryPosts(),
 * and getUserHistoryComments().
 *
 * Settings::resetCache() is called in setUp to clear the Support\Settings
 * static cache so that each test reads fresh values from the database.
 */
final class InfoRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private InfoRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('faq')->delete();
        DB::table('rules')->delete();
        Settings::resetCache();
        $this->repository = new InfoRepository;
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_resolve_rule_lang_id_returns_default_when_no_rules(): void
    {
        $langId = $this->ensureLanguage(['rule_lang' => 0]);

        $this->assertSame(6, $this->repository->resolveRuleLangId($langId));
    }

    public function test_resolve_rule_lang_id_returns_lang_id_when_has_rules(): void
    {
        $langId = $this->ensureLanguage(['rule_lang' => 1]);

        $this->assertSame($langId, $this->repository->resolveRuleLangId($langId));
    }

    public function test_faq_categories_returns_empty_when_none(): void
    {
        $langId = $this->ensureLanguage();

        $result = $this->repository->faqCategories($langId);

        $this->assertSame([], $result);
    }

    public function test_faq_categories_returns_categories_with_items(): void
    {
        $langId = $this->ensureLanguage();
        $this->insertFaq(['link_id' => 1, 'lang_id' => $langId, 'type' => 'categ', 'question' => 'Category 1', 'order' => 1]);
        $this->insertFaq(['link_id' => 2, 'lang_id' => $langId, 'type' => 'categ', 'question' => 'Category 2', 'order' => 2]);
        $this->insertFaq(['link_id' => 0, 'lang_id' => $langId, 'type' => 'item', 'categ' => 1, 'question' => 'Q1', 'answer' => 'A1', 'order' => 1]);
        $this->insertFaq(['link_id' => 0, 'lang_id' => $langId, 'type' => 'item', 'categ' => 2, 'question' => 'Q2', 'answer' => 'A2', 'order' => 2]);

        $result = $this->repository->faqCategories($langId);

        $this->assertArrayHasKey('1', $result);
        $this->assertArrayHasKey('2', $result);
        $this->assertSame('Category 1', $result['1']['title']);
        $this->assertArrayHasKey('items', $result['1']);
        $this->assertNotEmpty($result['1']['items']);
    }

    public function test_rules_returns_empty_array_when_none(): void
    {
        $langId = $this->ensureLanguage();

        $this->assertSame([], $this->repository->rules($langId));
    }

    public function test_rules_returns_rules_ordered_by_id(): void
    {
        $langId = $this->ensureLanguage();
        $firstId = $this->insertRule($langId, 'First Rule', 'text 1');
        $secondId = $this->insertRule($langId, 'Second Rule', 'text 2');

        $result = $this->repository->rules($langId);

        $this->assertCount(2, $result);
        $this->assertSame('First Rule', $result[0]['title']);
        $this->assertSame('Second Rule', $result[1]['title']);
    }

    public function test_faq_manage_data_returns_empty_when_none(): void
    {
        $result = $this->repository->faqManageData();

        $this->assertSame(['faqCateg' => [], 'faqOrphaned' => []], $result);
    }

    public function test_faq_manage_data_returns_categories_grouped_by_lang(): void
    {
        $langId = $this->ensureLanguage();
        $this->insertFaq(['link_id' => 1, 'lang_id' => $langId, 'type' => 'categ', 'question' => 'Cat 1', 'order' => 1]);
        $this->insertFaq(['link_id' => 0, 'lang_id' => $langId, 'type' => 'item', 'categ' => 1, 'question' => 'Item 1', 'order' => 1]);

        $result = $this->repository->faqManageData();

        $this->assertArrayHasKey($langId, $result['faqCateg']);
        $langCateg = $result['faqCateg'][$langId] ?? null;
        $this->assertNotNull($langCateg);
        $this->assertArrayHasKey(1, $langCateg);
        $this->assertSame('Cat 1', $langCateg[1]['title']);
        $this->assertArrayHasKey('items', $langCateg[1]);
    }

    public function test_faq_manage_data_detects_orphaned_items(): void
    {
        $langId = $this->ensureLanguage();
        // Item with categ=99 but no category with link_id=99 → orphaned
        $itemId = $this->insertFaq(['link_id' => 0, 'lang_id' => $langId, 'type' => 'item', 'categ' => 99, 'question' => 'Orphan', 'order' => 1]);

        $result = $this->repository->faqManageData();

        $this->assertArrayHasKey($langId, $result['faqOrphaned']);
        $langOrphaned = $result['faqOrphaned'][$langId] ?? null;
        $this->assertNotNull($langOrphaned);
        // Orphaned items are keyed by their FAQ item ID
        $this->assertArrayHasKey($itemId, $langOrphaned);
    }

    public function test_reorder_faq_updates_order(): void
    {
        $id1 = $this->insertFaq(['link_id' => 1, 'lang_id' => 6, 'type' => 'categ', 'question' => 'Q', 'order' => 1]);
        $id2 = $this->insertFaq(['link_id' => 2, 'lang_id' => 6, 'type' => 'categ', 'question' => 'Q2', 'order' => 2]);

        $this->repository->reorderFaq([$id1 => 5, $id2 => 3]);

        $this->assertSame(5, (int) DB::table('faq')->where('id', $id1)->value('order'));
        $this->assertSame(3, (int) DB::table('faq')->where('id', $id2)->value('order'));
    }

    public function test_update_faq_modifies_row(): void
    {
        $id = $this->insertFaq(['link_id' => 1, 'lang_id' => 6, 'type' => 'categ', 'question' => 'Old Q', 'order' => 1]);

        $this->repository->updateFaq($id, ['question' => 'New Q']);

        $this->assertSame('New Q', DB::table('faq')->where('id', $id)->value('question'));
    }

    public function test_delete_faq_removes_row(): void
    {
        $id = $this->insertFaq(['link_id' => 1, 'lang_id' => 6, 'type' => 'categ', 'question' => 'Delete Me', 'order' => 1]);

        $this->repository->deleteFaq($id);

        $this->assertSame(0, DB::table('faq')->where('id', $id)->count());
    }

    public function test_get_faq_by_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getFaqById(999999));
    }

    public function test_get_faq_by_id_returns_array_when_found(): void
    {
        $id = $this->insertFaq(['link_id' => 1, 'lang_id' => 6, 'type' => 'categ', 'question' => 'Find Me', 'order' => 1]);

        $result = $this->repository->getFaqById($id);

        $this->assertNotNull($result);
        $this->assertSame($id, (int) $result['id']);
        $this->assertSame('Find Me', $result['question']);
    }

    public function test_get_faq_categories_by_lang_returns_empty_when_none(): void
    {
        $langId = $this->ensureLanguage();

        $this->assertSame([], $this->repository->getFaqCategoriesByLang($langId));
    }

    public function test_get_faq_categories_by_lang_returns_categories(): void
    {
        $langId = $this->ensureLanguage();
        $this->insertFaq(['link_id' => 1, 'lang_id' => $langId, 'type' => 'categ', 'question' => 'Cat A', 'order' => 2]);
        $this->insertFaq(['link_id' => 2, 'lang_id' => $langId, 'type' => 'categ', 'question' => 'Cat B', 'order' => 1]);

        $result = $this->repository->getFaqCategoriesByLang($langId);

        $this->assertCount(2, $result);
        // Ordered by `order` ascending: Cat B (order 1) first
        $this->assertSame('Cat B', $result[0]['question']);
        $this->assertSame('Cat A', $result[1]['question']);
    }

    public function test_get_language_name_returns_empty_when_not_found(): void
    {
        $this->assertSame('', $this->repository->getLanguageName(999999));
    }

    public function test_get_language_name_returns_name_when_found(): void
    {
        $langId = $this->ensureLanguage(['lang_name' => 'TestLang']);

        $this->assertSame('TestLang', $this->repository->getLanguageName($langId));
    }

    public function test_get_faq_max_order_and_link_id_returns_zeros_when_none(): void
    {
        $langId = $this->ensureLanguage();

        $result = $this->repository->getFaqMaxOrderAndLinkId('categ', $langId);

        $this->assertSame(['maxorder' => 0, 'maxlinkid' => 0], $result);
    }

    public function test_get_faq_max_order_and_link_id_returns_maxima(): void
    {
        $langId = $this->ensureLanguage();
        $this->insertFaq(['link_id' => 1, 'lang_id' => $langId, 'type' => 'categ', 'question' => 'Q1', 'order' => 5]);
        $this->insertFaq(['link_id' => 3, 'lang_id' => $langId, 'type' => 'categ', 'question' => 'Q2', 'order' => 10]);

        $result = $this->repository->getFaqMaxOrderAndLinkId('categ', $langId);

        $this->assertSame(10, $result['maxorder']);
        $this->assertSame(3, $result['maxlinkid']);
    }

    public function test_insert_faq_creates_row(): void
    {
        $this->repository->insertFaq([
            'link_id' => 1,
            'lang_id' => 6,
            'type' => 'categ',
            'question' => 'Inserted Q',
            'answer' => '',
            'flag' => 1,
            'categ' => 0,
            'order' => 1,
        ]);

        $this->assertSame(1, DB::table('faq')->where('question', 'Inserted Q')->count());
    }

    public function test_about_nexus_returns_array_with_keys(): void
    {
        $result = $this->repository->aboutNexus();

        $this->assertArrayHasKey('languages', $result);
        $this->assertArrayHasKey('stylesheets', $result);
        $this->assertArrayHasKey('siteName', $result);
        $this->assertIsArray($result['languages']);
        $this->assertIsArray($result['stylesheets']);
        $this->assertIsString($result['siteName']);
    }

    public function test_donation_page_data_returns_disabled_when_setting_is_no(): void
    {
        Settings::resetCache();
        DB::table('settings')->where('name', 'main.donation')->update(['value' => 'no']);
        Settings::resetCache();

        $result = $this->repository->donationPageData();

        $this->assertFalse($result['enabled']);
    }

    public function test_donation_page_data_returns_enabled_when_setting_is_yes(): void
    {
        Settings::resetCache();
        DB::table('settings')->where('name', 'main.donation')->update(['value' => 'yes']);
        Settings::resetCache();

        $result = $this->repository->donationPageData();

        $this->assertTrue($result['enabled']);
    }

    public function test_donation_page_data_includes_custom_text(): void
    {
        Settings::resetCache();
        DB::table('settings')->updateOrInsert(
            ['name' => 'misc.donation_custom'],
            ['value' => 'Custom donation text', 'autoload' => 1]
        );
        Settings::resetCache();

        $result = $this->repository->donationPageData();

        $this->assertSame('Custom donation text', $result['custom']);
        $this->assertTrue($result['showCustom']);
        $this->assertTrue($result['showAny']);
    }

    public function test_get_user_history_posts_returns_empty_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getUserHistoryPosts($user->id, 5, 10, 'userhistory.php');

        $this->assertSame(0, $result['postcount']);
        $this->assertSame([], $result['posts']);
        $this->assertSame([], $result['editorNames']);
    }

    public function test_get_user_history_posts_returns_posts_with_count(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $forumId = $this->ensureForum();
        $topicId = $this->ensureTopic($forumId);
        DB::table('posts')->insert([
            'topicid' => $topicId,
            'userid' => $user->id,
            'added' => now()->toDateTimeString(),
            'body' => 'test post body',
        ]);

        $result = $this->repository->getUserHistoryPosts($user->id, 10, 10, 'userhistory.php');

        $this->assertGreaterThan(0, $result['postcount']);
        $this->assertNotEmpty($result['posts']);
    }

    public function test_get_user_history_comments_returns_empty_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getUserHistoryComments($user->id, 10, 'userhistory.php');

        $this->assertSame(0, $result['commentcount']);
        $this->assertSame([], $result['comments']);
        $this->assertSame([], $result['commentPageMap']);
    }

    public function test_get_user_history_comments_returns_comments_with_count(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrentId = $this->ensureTorrent($user->id);
        DB::table('comments')->insert([
            'user' => $user->id,
            'torrent' => $torrentId,
            'text' => 'test comment',
            'added' => now()->toDateTimeString(),
        ]);

        $result = $this->repository->getUserHistoryComments($user->id, 10, 'userhistory.php');

        $this->assertGreaterThan(0, $result['commentcount']);
        $this->assertNotEmpty($result['comments']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ensureLanguage(array $overrides = []): int
    {
        $id = (int) DB::table('language')->max('id');
        if ($id === 0) {
            $id = (int) DB::table('language')->insertGetId(array_merge([
                'lang_name' => 'TestLang',
                'flagpic' => 'test.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => 'test',
                'trans_state' => 'up-to-date',
            ], $overrides));

            return $id;
        }

        // Update existing language with overrides
        if ($overrides !== []) {
            DB::table('language')->where('id', $id)->update($overrides);
        }

        return $id;
    }

    /** @param  array<string, mixed>  $data */
    private function insertFaq(array $data): int
    {
        return (int) DB::table('faq')->insertGetId(array_merge([
            'link_id' => 0,
            'lang_id' => 6,
            'type' => 'item',
            'question' => 'Question',
            'answer' => 'Answer',
            'flag' => 1,
            'categ' => 0,
            'order' => 0,
        ], $data));
    }

    private function insertRule(int $langId, string $title, string $text): int
    {
        return (int) DB::table('rules')->insertGetId([
            'lang_id' => $langId,
            'title' => $title,
            'text' => $text,
        ]);
    }

    private function ensureForum(): int
    {
        $id = (int) DB::table('forums')->max('id');
        if ($id === 0) {
            $id = (int) DB::table('forums')->insertGetId([
                'name' => 'Test Forum',
                'minclassread' => 0,
                'minclasswrite' => 0,
                'minclasscreate' => 0,
            ]);
        }

        return $id;
    }

    private function ensureTopic(int $forumId): int
    {
        /** @var User $user */
        $user = User::factory()->create();

        return (int) DB::table('topics')->insertGetId([
            'forumid' => $forumId,
            'userid' => $user->id,
            'subject' => 'Test Topic',
            'lastpost' => 0,
        ]);
    }

    private function ensureTorrent(int $ownerId): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
        ]);
    }
}
