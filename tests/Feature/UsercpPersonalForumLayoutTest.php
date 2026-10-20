<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TrackerUrl;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class UsercpPersonalForumLayoutTest extends TestCase
{
    use DatabaseTransactions;

    private int $trackerUrlId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trackerUrlId = (int) TrackerUrl::query()->create([
            'url' => 'https://tracker-layout.example.test/announce',
            'enabled' => 1,
            'is_default' => 0,
            'priority' => 99,
        ])->id;
        $this->setTooltipSetting(true);
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_personal_field_names_and_checked_selected_values_match_the_php8_baseline(): void
    {
        $form = $this->personalForm($this->personalUser());

        $this->assertSame([
            'acceptpms',
            'avatar',
            'avatarimg',
            'commentpm',
            'country',
            'deletepms',
            'gender',
            'info',
            'notifs[hr_reached]',
            'notifs[topic_reply]',
            'parked',
            'savatar',
            'savepms',
            'tracker_url_id',
        ], $this->inputNames($form));
        $this->assertSame([
            'acceptpms',
            'commentpm',
            'deletepms',
            'gender',
            'notifs[topic_reply]',
            'parked',
            'savepms',
        ], $this->checkedInputNames($form));
        $this->assertSame(['country', 'tracker_url_id'], $this->selectedOptionNames($form));
    }

    public function test_forum_field_names_and_checked_selected_values_match_the_php8_baseline_with_tooltip(): void
    {
        $form = $this->forumForm($this->forumUser());

        $this->assertSame([
            'avatars',
            'clicktopic',
            'postsperpage',
            'signature',
            'signatures',
            'topicsperpage',
            'ttlastpost',
        ], $this->inputNames($form));
        $this->assertSame([
            'avatars',
            'clicktopic',
            'signatures',
            'ttlastpost',
        ], $this->checkedInputNames($form));
        $this->assertSame([], $this->selectedOptionNames($form));
    }

    public function test_forum_field_names_match_the_php8_baseline_without_tooltip(): void
    {
        $this->setTooltipSetting(false);
        $form = $this->forumForm($this->forumUser());

        $this->assertSame([
            'avatars',
            'clicktopic',
            'postsperpage',
            'signature',
            'signatures',
            'topicsperpage',
        ], $this->inputNames($form));
    }

    public function test_saved_personal_and_forum_pages_render_success_status(): void
    {
        foreach (['personal', 'forum'] as $section) {
            $response = $this->withNexusCookie($this->personalUser())->get(
                '/usercp?action='.$section.'&type=saved'
            );

            $response->assertOk()
                ->assertSee('role="status"', false)
                ->assertSee('Settings saved.')
                ->assertDontSee('Saved!');
        }
    }

    public function test_personal_and_forum_render_the_required_sections_and_save_button(): void
    {
        $personal = $this->withNexusCookie($this->personalUser())->get('/usercp?action=personal');
        $forum = $this->withNexusCookie($this->forumUser())->get('/usercp?action=forum');

        foreach ([$personal, $forum] as $response) {
            $response->assertOk()
                ->assertSee('<button type="submit" class="nx-btn nx-btn--primary" data-submit-once>Save settings</button>', false)
                ->assertDontSee('PRESS ONLY ONCE');
        }
        foreach (['account', 'profile', 'avatar', 'about'] as $section) {
            $personal->assertSee('id="ucp-sec-'.$section.'"', false);
        }
        foreach (['forum-pages', 'forum-display', 'signature'] as $section) {
            $forum->assertSee('id="ucp-sec-'.$section.'"', false);
        }
    }

    private function personalUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'class' => 1,
            'parked' => 1,
            'acceptpms' => 1,
            'deletepms' => 1,
            'savepms' => 1,
            'commentpm' => 1,
            'notifs' => '[topic_reply]',
            'gender' => 0,
            'country' => 107,
            'tracker_url_id' => $this->trackerUrlId,
        ]);

        return $user;
    }

    private function forumUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'class' => 1,
            'avatars' => 1,
            'signatures' => 1,
            'showlastpost' => 1,
            'clicktopic' => 0,
        ]);

        return $user;
    }

    private function setTooltipSetting(bool $enabled): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'tweak.enabletooltip'],
            ['value' => $enabled ? 'yes' : 'no', 'autoload' => 1]
        );
        Settings::resetCache();
    }

    private function personalForm(User $user): string
    {
        return $this->formMarkup($this->withNexusCookie($user)->get('/usercp?action=personal')->getContent(), 'personal');
    }

    private function forumForm(User $user): string
    {
        return $this->formMarkup($this->withNexusCookie($user)->get('/usercp?action=forum')->getContent(), 'forum');
    }

    private function formMarkup(string|false $html, string $section): string
    {
        $this->assertIsString($html);
        $matched = preg_match(
            '~<form\b[^>]*action="/web/usercp/'.$section.'"[^>]*>(.*?)</form>~s',
            $html,
            $form
        );
        $this->assertSame(1, $matched);

        return $form[1] ?? '';
    }

    /** @return list<string> */
    private function inputNames(string $form): array
    {
        preg_match_all('~\bname="([^"]+)"~', $form, $matches);
        $names = array_values(array_unique(array_map('strval', $matches[1] ?? [])));
        sort($names, SORT_STRING);

        return $names;
    }

    /** @return list<string> */
    private function checkedInputNames(string $form): array
    {
        preg_match_all('~<input\b(?=[^>]*\bname="([^"]+)")(?=[^>]*\bchecked(?:\s|=|>))[^>]*>~s', $form, $matches);
        $names = array_values(array_unique(array_map('strval', $matches[1] ?? [])));
        sort($names, SORT_STRING);

        return $names;
    }

    /** @return list<string> */
    private function selectedOptionNames(string $form): array
    {
        preg_match_all('~<select\b[^>]*\bname="([^"]+)"[^>]*>(.*?)</select>~s', $form, $selects);
        $names = [];
        foreach ($selects[1] ?? [] as $index => $name) {
            if (str_contains((string) ($selects[2][$index] ?? ''), ' selected')) {
                $names[] = (string) $name;
            }
        }
        sort($names, SORT_STRING);

        return $names;
    }
}
