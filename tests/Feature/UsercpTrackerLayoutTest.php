<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class UsercpTrackerLayoutTest extends TestCase
{
    use DatabaseTransactions;

    private const TRACKER_FIELD_NAMES = [
        'appendnew',
        'appendpicked',
        'appendpromotion',
        'appendsticky',
        'bmicon',
        'dlicon',
        'emailnotif',
        'fontsize',
        'inclbookmarked',
        'incldead',
        'pmnotif',
        'pmnum',
        'sbnum',
        'sbrefresh',
        'showcomment',
        'showcomnum',
        'showdescription',
        'showlastcom',
        'sitelanguage',
        'spstate',
        'stylesheet',
        'theme',
        'timetype',
        'tooltip',
        'torrentsperpage',
    ];

    private int $categoryId;

    /** @var array{medium: int, codec: int, standard: int} */
    private array $taxonomyIds;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('searchbox')->updateOrInsert(['id' => 99], [
            'name' => 'tracker-layout-contract',
            'showsubcat' => 1,
            'showsource' => 0,
            'showmedium' => 1,
            'showcodec' => 1,
            'showstandard' => 1,
            'showprocessing' => 0,
            'showaudiocodec' => 0,
            'catsperrow' => 10,
            'catpadding' => 7,
            'custom_fields' => '',
            'custom_fields_display_name' => '',
            'custom_fields_display' => '',
        ]);

        DB::table('categories')->where('mode', 99)->delete();
        $this->categoryId = (int) DB::table('categories')->insertGetId([
            'mode' => 99,
            'class_name' => 'tracker_layout_category',
            'name' => 'Tracker layout category',
            'image' => 'catsprites.png',
            'sort_index' => 10,
            'icon_id' => 0,
        ]);

        foreach (['media', 'codecs', 'standards'] as $table) {
            DB::table($table)->whereIn('mode', [0, 99])->delete();
        }
        $this->taxonomyIds = [
            'medium' => (int) DB::table('media')->insertGetId(['name' => 'Tracker layout medium', 'sort_index' => 10, 'mode' => 99]),
            'codec' => (int) DB::table('codecs')->insertGetId(['name' => 'Tracker layout codec', 'sort_index' => 10, 'mode' => 99]),
            'standard' => (int) DB::table('standards')->insertGetId(['name' => 'Tracker layout standard', 'sort_index' => 10, 'mode' => 99]),
        ];

        foreach ([
            'main.browsecat' => '99',
            'smtp.emailnotify' => 'yes',
            'smtp.smtptype' => 'internal',
            'main.showshoutbox' => 'yes',
            'tweak.enabletooltip' => 'yes',
        ] as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'autoload' => 1]
            );
        }

        Settings::resetCache();
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_tracker_field_names_match_the_php8_baseline(): void
    {
        $html = $this->trackerForm($this->trackerUser());
        $actualFieldNames = $this->inputNames($html);
        $expectedFieldNames = array_merge(self::TRACKER_FIELD_NAMES, [
            'cat'.$this->categoryId,
            'cod'.$this->taxonomyIds['codec'],
            'med'.$this->taxonomyIds['medium'],
            'sta'.$this->taxonomyIds['standard'],
        ]);
        sort($expectedFieldNames, SORT_STRING);

        $this->assertSame($expectedFieldNames, $actualFieldNames);

        $expectedCheckedNames = [
            'appendpicked',
            'appendpromotion',
            'appendsticky',
            'cat'.$this->categoryId,
            'cod'.$this->taxonomyIds['codec'],
            'dlicon',
            'emailnotif',
            'med'.$this->taxonomyIds['medium'],
            'pmnotif',
            'showcomnum',
            'showdescription',
            'sta'.$this->taxonomyIds['standard'],
            'timetype',
            'tooltip',
        ];
        sort($expectedCheckedNames, SORT_STRING);
        $this->assertSame($expectedCheckedNames, $this->checkedInputNames($html));
    }

    public function test_saved_tracker_renders_a_success_status(): void
    {
        $response = $this->withNexusCookie($this->trackerUser())->get('/usercp?action=tracker&type=saved');

        $response->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('Settings saved.')
            ->assertDontSee('Saved!');
    }

    public function test_tracker_renders_all_five_sections(): void
    {
        $response = $this->withNexusCookie($this->trackerUser())->get('/usercp?action=tracker');

        $response->assertOk();
        foreach (['catalog', 'appearance', 'messages', 'torrent-list', 'torrent-page'] as $section) {
            $response->assertSee('id="ucp-sec-'.$section.'"', false);
        }
    }

    public function test_tracker_uses_the_named_save_button(): void
    {
        $response = $this->withNexusCookie($this->trackerUser())->get('/usercp?action=tracker');

        $response->assertOk()
            ->assertSee('<button type="submit" class="nx-btn nx-btn--primary" data-submit-once>Save settings</button>', false)
            ->assertDontSee('PRESS ONLY ONCE');
    }

    private function trackerUser(): User
    {
        $notifs = '[pm][email][cat'.$this->categoryId.'][med'.$this->taxonomyIds['medium'].'][cod'.$this->taxonomyIds['codec'].'][sta'.$this->taxonomyIds['standard'].']';

        /** @var User $user */
        $user = User::factory()->create([
            'class' => 1,
            'notifs' => $notifs,
            'showdescription' => 1,
            'showcomment' => 0,
            'timetype' => 0,
            'tooltip' => 2,
            'appendsticky' => 1,
            'appendnew' => 0,
            'appendpromotion' => 1,
            'appendpicked' => 1,
            'dlicon' => 1,
            'bmicon' => 0,
            'showcomnum' => 1,
        ]);

        return $user;
    }

    private function trackerForm(User $user): string
    {
        $response = $this->withNexusCookie($user)->get('/usercp?action=tracker');
        $response->assertOk();

        $content = $response->getContent();
        if (! is_string($content)) {
            throw new \RuntimeException('Could not read the tracker response.');
        }

        return $this->formMarkup($content);
    }

    private function formMarkup(string $html): string
    {
        $matched = preg_match('~<form\b[^>]*action="/web/usercp/tracker"[^>]*>(.*?)</form>~s', $html, $form);
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
}
