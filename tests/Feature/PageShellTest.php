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
}
