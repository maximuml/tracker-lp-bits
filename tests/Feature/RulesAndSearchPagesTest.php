<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class RulesAndSearchPagesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rule_titles_render_legacy_font_markup_as_text_and_emphasis(): void
    {
        $user = User::factory()->create();
        $langId = (int) (DB::table('rules')->value('lang_id') ?? 6);
        DB::table('rules')->delete();
        DB::table('rules')->insert([
            ['lang_id' => $langId, 'title' => 'General rules - <font class=striking>Breaking these &amp; rules bans you!</font>', 'text' => 'Body one'],
            ['lang_id' => $langId, 'title' => 'Plain title', 'text' => 'Body two'],
        ]);
        Cache::forget('en_rules');

        $response = $this->withNexusCookie($user)->get('/web/rules');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringNotContainsString('&lt;font', $html);
        $this->assertStringContainsString('General rules - <span class="striking">Breaking these &amp; rules bans you!</span></h2>', $html);
        $this->assertStringContainsString('>Plain title</h2>', $html);
    }

    public function test_empty_search_shows_a_labelled_search_form(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/search');

        $response->assertOk();
        $html = (string) $response->getContent();
        foreach ([
            '<h1 class="text-left">Global Search</h1>',
            'action="/web/search" method="get" role="search"',
            '<label for="nx-search-keyword">',
            'name="search" required',
            '<label for="nx-search-area">',
            'name="search_area"',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }
}
