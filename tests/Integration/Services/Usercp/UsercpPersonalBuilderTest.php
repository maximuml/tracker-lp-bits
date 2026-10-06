<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Usercp;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Repositories\UsercpLookupRepository;
use App\Services\Usercp\UsercpPersonalBuilder;
use App\ViewModels\Usercp\UsercpPersonalSection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Kills escaped mutants in the personal-section builder (formerly
 * UsercpPageService::buildPersonalSection): option lists (country,
 * tracker URL, bitbucket), notif checkboxes, yes/no toggles, enums.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UsercpPersonalBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestSettings([
            'BASEURL' => 'http://test.com',
            'enablebitbucket_main' => 'yes',
        ]);
    }

    private function builder(): UsercpPersonalBuilder
    {
        return new UsercpPersonalBuilder(app(UsercpLookupRepository::class));
    }

    /** @param  array<string, mixed>  $overrides */
    /** @param  array<string, mixed>  $overrides */
    private function section(array $overrides = []): UsercpPersonalSection
    {
        return $this->builder()->build(array_merge([
            'parked' => 'yes',
            'acceptpms' => 1,
            'deletepms' => 'yes',
            'savepms' => 'no',
            'commentpm' => 'yes',
            'notifs' => '[topic_reply]',
            'gender' => 1,
            'tracker_url_id' => 0,
            'country' => 0,
            'avatar' => 'http://x/av.png',
            'info' => 'bio text',
        ], $overrides));
    }

    public function test_yes_no_toggles_and_enums(): void
    {
        $s = $this->section();

        $this->assertTrue($s->parked);
        $this->assertSame('friends', $s->acceptpms);
        $this->assertTrue($s->deletepms);
        $this->assertFalse($s->savepms);
        $this->assertTrue($s->commentpm);
        $this->assertSame('Female', $s->gender);
        $this->assertTrue($s->enableBitbucket);
        $this->assertSame('bio text', $s->info);
        $this->assertSame('http://x/av.png', $s->avatar);
    }

    public function test_notif_checkboxes_track_marker_presence(): void
    {
        // notifs contains only [topic_reply]: that checkbox checked, the
        // other option (hr_reached) unchecked.
        $s = $this->section(['notifs' => '[topic_reply]']);
        $checked = array_column($s->notifCheckboxes, 'checked', 'name');

        $this->assertTrue($checked['notifs[topic_reply]']);
        $this->assertFalse($checked['notifs[hr_reached]']);
    }

    public function test_null_notifs_checks_everything(): void
    {
        $s = $this->section(['notifs' => null]);
        $checked = array_column($s->notifCheckboxes, 'checked', 'name');

        $this->assertTrue($checked['notifs[topic_reply]']);
        $this->assertTrue($checked['notifs[hr_reached]']);
    }

    public function test_country_and_bitbucket_options(): void
    {
        $countryId = DB::table('countries')->insertGetId(['name' => 'Testland ZZ']);
        DB::table('bitbucket')->insert(['owner' => 0, 'name' => 'zzgallery', 'public' => '1']);
        DB::table('bitbucket')->insert(['owner' => 0, 'name' => 'zzhidden', 'public' => '0']);

        $s = $this->section();

        $this->assertArrayHasKey('0', $s->countryOptions);
        $this->assertSame('Testland ZZ', $s->countryOptions[(string) $countryId]);
        $this->assertSame('zzgallery', $s->bitbucketOptions['http://test.com/bitbucket/zzgallery']);
        $this->assertArrayNotHasKey('http://test.com/bitbucket/zzhidden', $s->bitbucketOptions);
        $this->assertStringContainsString('/pic/', $s->defaultAvatarUrl);
    }

    public function test_default_avatar_url_uses_base_url(): void
    {
        $s = $this->section();

        $this->assertSame('http://test.com/pic/default_avatar.png', $s->defaultAvatarUrl);
    }

    public function test_lookup_repo_is_mockable(): void
    {
        /** @var UsercpLookupRepositoryInterface&MockInterface $lookup */
        $lookup = \Mockery::mock(UsercpLookupRepositoryInterface::class);
        $lookup->shouldReceive('getCountryOptions')->once()->andReturn([(object) ['id' => 9, 'name' => 'Mocked']]);
        $lookup->shouldReceive('getBitbucketOptions')->once()->andReturn([(object) ['name' => 'bb']]);

        $s = (new UsercpPersonalBuilder($lookup))->build([]);

        $this->assertSame('Mocked', $s->countryOptions['9']);
        $this->assertSame('bb', $s->bitbucketOptions['http://test.com/bitbucket/bb']);
    }
}
