<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Usercp;

use App\Services\Usercp\UsercpForumBuilder;
use App\ViewModels\Usercp\UsercpForumSection;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Kills escaped mutants in the forum-section builder (formerly
 * UsercpPageService::buildForumSection): boolean yes/no coercion of every
 * checkbox field, per-page ints, clicktopic enum mapping, tooltip setting.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UsercpForumBuilderTest extends TestCase
{
    use MakesCurrentUser;
    use SeedsLegacySettings;

    private function builder(): UsercpForumBuilder
    {
        return new UsercpForumBuilder;
    }

    /** @param  array<string, mixed>  $overrides */
    private function section(array $overrides = []): UsercpForumSection
    {
        return $this->builder()->build($this->curUser(array_merge([
            'topicsperpage' => 25,
            'postsperpage' => 10,
            'avatars' => 'yes',
            'signatures' => 'no',
            'showlastpost' => 'yes',
            'clicktopic' => 0,
            'signature' => 'sig text',
        ], $overrides)));
    }

    public function test_maps_yes_no_fields_and_per_page_values(): void
    {
        $this->seedTestSettings(['enabletooltip_tweak' => 'yes']);

        $s = $this->section();

        $this->assertSame(25, $s->topicsPerPage);
        $this->assertSame(10, $s->postsPerPage);
        $this->assertTrue($s->avatars);
        $this->assertFalse($s->signatures);
        $this->assertTrue($s->showLastPost);
        $this->assertSame('firstpage', $s->clicktopic);
        $this->assertSame('sig text', $s->signature);
        $this->assertTrue($s->showTooltipSetting);
        $this->assertStringStartsWith('form', $s->formId);
        $this->assertSame(10, strlen($s->formId));
    }

    public function test_clicktopic_lastpage_and_flag_negations(): void
    {
        $this->seedTestSettings(['enabletooltip_tweak' => 'no']);

        $s = $this->section([
            'avatars' => 'no',
            'signatures' => 'yes',
            'showlastpost' => 'no',
            'clicktopic' => 1,
        ]);

        $this->assertFalse($s->avatars);
        $this->assertTrue($s->signatures);
        $this->assertFalse($s->showLastPost);
        $this->assertSame('lastpage', $s->clicktopic);
        $this->assertFalse($s->showTooltipSetting);
    }

    public function test_missing_keys_fall_back_to_defaults(): void
    {
        $this->seedTestSettings(['enabletooltip_tweak' => 'no']);

        $s = $this->builder()->build($this->curUser([]));

        $this->assertSame(0, $s->topicsPerPage);
        $this->assertSame(0, $s->postsPerPage);
        $this->assertFalse($s->avatars);
        $this->assertFalse($s->signatures);
        $this->assertFalse($s->showLastPost);
        $this->assertSame('firstpage', $s->clicktopic);
        $this->assertSame('', $s->signature);
    }
}
