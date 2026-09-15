<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\TorrentSearch;

use App\Repositories\TorrentSearch\TaxonomySelectionParser;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class TaxonomySelectionParserTest extends TestCase
{
    private TaxonomySelectionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TaxonomySelectionParser;
    }

    /** @return array<string, mixed> */
    private function resolve(array $searchParams = [], array $CURUSER = [], bool $hasSearchParams = true): array
    {
        return $this->parser->resolve(
            $searchParams,
            $CURUSER,
            $hasSearchParams,
            true,  // showsubcat
            true, true, true, true, true, true, // showsource..showaudiocodec
            [['id' => 1], ['id' => 2]],          // cats
            [['id' => 11]],                       // sources
            [['id' => 21]],                       // media
            [['id' => 31]],                       // codecs
            [['id' => 41]],                       // standards
            [['id' => 51]],                       // processings
            [['id' => 61]],                       // audiocodecs
        );
    }

    public function test_no_selection_leaves_lists_empty(): void
    {
        $r = $this->resolve([]);

        $this->assertFalse((bool) $r['all']);
        $this->assertSame([], $r['wherecatina']);
        $this->assertSame([], $r['wheresourceina']);
    }

    public function test_collects_taxonomy_ids_from_params(): void
    {
        $r = $this->resolve(['cat1' => 1, 'source11' => 1, 'audiocodec61' => 1]);

        $this->assertSame([1], $r['wherecatina']);
        $this->assertSame([11], $r['wheresourceina']);
        $this->assertSame([61], $r['whereaudiocodecina']);
        $this->assertSame([], $r['wheremediumina']);
        $this->assertStringContainsString('cat1=1&', $r['addparam']);
        $this->assertStringContainsString('source11=1&', $r['addparam']);
        // not all items selected => not "all"
        $this->assertFalse((bool) $r['all']);
    }

    public function test_single_category_click_collects_category(): void
    {
        $r = $this->resolve(['cat' => '2']);

        $this->assertSame([2], $r['wherecatina']);
        $this->assertStringContainsString('cat=2&', $r['addparam']);
    }

    public function test_all_param_skips_collection(): void
    {
        $r = $this->resolve(['all' => '1', 'cat1' => 1]);

        $this->assertTrue((bool) $r['all']);
        $this->assertSame([], $r['wherecatina']);
    }

    public function test_notifs_collect_from_user_preferences(): void
    {
        // notifs string marks cat1 + source11 selected, cat2 not
        $r = $this->resolve(
            [],
            ['notifs' => '[cat1][sou11]', 'id' => 5, 'username' => 'u', 'ip' => '127.0.0.1'],
            false, // no search params -> read notifs
        );

        $this->assertSame([1], $r['wherecatina']);
        $this->assertSame([11], $r['wheresourceina']);
        $this->assertFalse((bool) $r['all']);
    }
}
