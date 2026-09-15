<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\TorrentSearch;

use App\Repositories\TorrentSearch\SortingBuilder;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class SortingBuilderTest extends TestCase
{
    private SortingBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new SortingBuilder;
    }

    public function test_defaults_to_id_desc_without_sort_params(): void
    {
        $result = $this->builder->build([]);

        $this->assertSame('', $result['column']);
        $this->assertSame('', $result['ascdesc']);
        $this->assertSame('', $result['pagerlink']);
        $this->assertSame([['pos_state', 'desc'], ['torrents.id', 'desc']], $result['orderBy']);
    }

    public function test_builds_column_sort_ascending(): void
    {
        $result = $this->builder->build(['sort' => '7', 'type' => 'asc']);

        $this->assertSame('seeders', $result['column']);
        $this->assertSame('ASC', $result['ascdesc']);
        $this->assertSame('asc', $result['linkascdesc']);
        $this->assertSame([['pos_state', 'desc'], ['torrents.seeders', 'ASC']], $result['orderBy']);
        $this->assertSame('sort=7&type=asc&', $result['pagerlink']);
    }

    public function test_owner_sort_joins_username_column(): void
    {
        $result = $this->builder->build(['sort' => '9', 'type' => 'desc']);

        $this->assertSame('owner', $result['column']);
        $this->assertSame([
            ['pos_state', 'desc'],
            ['torrents.anonymous', 'asc'],
            ['users.username', 'DESC'],
        ], $result['orderBy']);
    }

    public function test_unknown_sort_column_falls_back_to_id(): void
    {
        $result = $this->builder->build(['sort' => '99', 'type' => 'desc']);

        $this->assertSame('id', $result['column']);
        $this->assertSame([['pos_state', 'desc'], ['torrents.id', 'DESC']], $result['orderBy']);
    }

    public function test_unknown_sort_type_defaults_desc(): void
    {
        $result = $this->builder->build(['sort' => '5', 'type' => 'bogus']);

        $this->assertSame('size', $result['column']);
        $this->assertSame('DESC', $result['ascdesc']);
        $this->assertSame('desc', $result['linkascdesc']);
    }

    public function test_sort_without_type_uses_default_order(): void
    {
        $result = $this->builder->build(['sort' => '4']);

        $this->assertSame('', $result['column']);
        $this->assertSame([['pos_state', 'desc'], ['torrents.id', 'desc']], $result['orderBy']);
        $this->assertSame('', $result['pagerlink']);
    }
}
