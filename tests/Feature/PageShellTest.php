<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserClass;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class PageShellTest extends TestCase
{
    use DatabaseTransactions;

    public function test_log_page_has_visible_heading_and_tabs(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/log?action=dailylog');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Daily Log<\/h1>/', $html);
        $this->assertStringContainsString('class="nx-tabs"', $html);
        $this->assertStringContainsString('nx-tabs__link--active', $html);
        $this->assertStringContainsString('?action=chronicle', $html);
        $this->assertStringNotContainsString('id="logmenu"', $html);
        // Regression: a missing brace in the Blade echo used to leak raw
        // `{{ __('log.submit_search') }}` into the button value.
        $this->assertStringContainsString('value="Search"', $html);
        $this->assertStringNotContainsString("{{ __('log.submit_search')}", $html);
    }

    public function test_log_empty_state_is_a_card_not_bare_text(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/log?action=news&query=zzz-no-such-news');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('<h1 class="nx-page__title">News</h1>', $html);
    }

    public function test_invite_page_has_visible_heading_tabs_and_action(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/invite?id='.$user->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Invite System</h1>', $html);
        $this->assertStringContainsString('class="nx-tabs"', $html);
        $this->assertStringContainsString('menu=sent', $html);
        $this->assertStringContainsString('type=new', $html);
        $this->assertStringNotContainsString('id="invitenav"', $html);
    }

    public function test_topten_page_has_visible_heading_and_type_tabs(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/topten?type=2');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('<h1 class="nx-page__title">Top 10</h1>', $html);
        $this->assertStringContainsString('class="nx-tabs"', $html);
        $this->assertStringContainsString('/web/topten?type=3', $html);
    }

    public function test_shoutbox_history_page_has_visible_heading_and_filter_fields(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/shoutbox_history');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Shoutbox history<\/h1>/', $html);
        $this->assertStringContainsString('name="user"', $html);
        $this->assertStringContainsString('name="from"', $html);
        $this->assertStringContainsString('name="to"', $html);
        $this->assertStringContainsString('name="search"', $html);
    }

    public function test_latestcomments_page_has_visible_heading(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/latestcomments');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Latest Comments<\/h1>/', $html);
    }

    public function test_complains_page_has_visible_heading(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/complains');

        $response->assertOk();
        $this->assertStringContainsString('nx-page__title', (string) $response->getContent());
    }

    public function test_donated_page_has_localized_heading_and_labelled_form(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/donated');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Update users donated amounts<\/h1>/', $html);
        $this->assertStringContainsString('for="donated-username"', $html);
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('name="donated"', $html);
    }

    public function test_rules_page_has_visible_heading(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/rules');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Rules<\/h1>/', (string) $response->getContent());
    }

    public function test_faq_page_has_visible_heading(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/faq');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">FAQ<\/h1>/', (string) $response->getContent());
    }

    public function test_useragreement_page_has_visible_heading(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/useragreement');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">User Agreement<\/h1>/', (string) $response->getContent());
    }

    public function test_aboutnexus_page_has_visible_heading(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/aboutnexus');

        $response->assertOk();
        $this->assertStringContainsString('nx-page__title', (string) $response->getContent());
    }

    public function test_getrss_page_has_visible_heading_and_form_fields(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/getrss');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">RSS Feeds<\/h1>/', $html);
        $this->assertStringContainsString('action="/web/rss/generate"', $html);
        $this->assertStringContainsString('name="inclbookmarked"', $html);
        $this->assertStringContainsString('name="showrows"', $html);
    }

    public function test_staff_page_has_visible_heading(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/staff');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Staff<\/h1>/', $html);
    }

    public function test_tags_page_has_visible_heading_and_labelled_test_form(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/tags');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Tags<\/h1>/', $html);
        $this->assertStringContainsString('for="nx-tag-test"', $html);
        $this->assertStringContainsString('name="test"', $html);
    }

    public function test_users_page_has_visible_heading_and_labelled_search(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/users');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Users<\/h1>/', $html);
        $this->assertStringContainsString('for="nx-users-search"', $html);
        $this->assertStringContainsString('name="search"', $html);
        $this->assertStringContainsString('name="class"', $html);
        $this->assertStringContainsString('name="country"', $html);
    }

    public function test_uploaders_page_has_single_heading_and_month_subtitle(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/uploaders');

        $response->assertOk();
        $html = (string) $response->getContent();
        // Regression: the page used to render a hidden h1 plus a second,
        // centred h1 with the month.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Uploaders<\/h1>/', $html);
        $this->assertStringContainsString('nx-page__sub', $html);
        $this->assertStringContainsString('name="year"', $html);
        $this->assertStringContainsString('name="month"', $html);
    }

    public function test_viewsnatches_page_has_heading_subtitle_and_view_action(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);
        $torrent = Torrent::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/viewsnatches?id='.$torrent->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">Snatch Details<\/h1>/', $html);
        $this->assertStringContainsString('nx-page__sub', $html);
        $this->assertStringContainsString('href="/web/details/'.(int) $torrent->id.'"', $html);
    }

    public function test_bonus_log_page_has_heading_and_profile_action(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/web/bonus-log?uid='.$user->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<h1 class="nx-page__title">User bonus details<\/h1>/', $html);
        $this->assertStringContainsString('nx-page__sub', $html);
        $this->assertStringContainsString('href="/userdetails?id='.(int) $user->id.'"', $html);
        $this->assertStringContainsString('name="category"', $html);
        $this->assertStringContainsString('name="business_type"', $html);
    }

    public function test_userdetails_page_uses_page_shell(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $response = $this->withNexusCookie($user)->get('/userdetails?id='.$user->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('nx-page nx-page--list', $html);
        $this->assertStringContainsString('nx-page__title', $html);
        $this->assertStringNotContainsString('<h1 class="nx-sr-only">', $html);
    }
}
