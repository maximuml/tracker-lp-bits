<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserClass;
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
}
