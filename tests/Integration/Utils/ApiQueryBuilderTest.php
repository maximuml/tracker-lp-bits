<?php

declare(strict_types=1);

namespace Tests\Integration\Utils;

use App\Http\Resources\TorrentResource;
use App\Models\Torrent;
use App\Utils\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression: array-valued request parameters (?includes[]=x, ?sorts[]=y)
 * reached explode()/(string) casts and crashed with TypeError/ErrorException
 * under strict_types. Both comma-separated and PHP array syntax must be
 * accepted.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ApiQueryBuilderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    private function builderFor(string $queryString): ApiQueryBuilder
    {
        $request = Request::create('/api/v1/torrents'.$queryString, 'GET');

        return ApiQueryBuilder::for(TorrentResource::NAME, Torrent::query(), $request);
    }

    /** @return array<string, string> */
    private function orderMap(Builder $query): array
    {
        /** @var array<int, array{column: string, direction: string}> $orders */
        $orders = $query->getQuery()->orders ?? [];

        return array_column($orders, 'direction', 'column');
    }

    public function test_includes_accept_array_syntax(): void
    {
        $query = $this->builderFor('?includes[]=author&includes[]=comments.user')
            ->allowIncludes(['author', 'comments.user'])
            ->build();

        $this->assertArrayHasKey('author', $query->getEagerLoads());
        $this->assertArrayHasKey('comments.user', $query->getEagerLoads());
    }

    public function test_includes_accept_csv_string(): void
    {
        $builder = $this->builderFor('?includes=author,comments.user')
            ->allowIncludes(['author', 'comments.user', 'owner']);

        $this->assertTrue($builder->hasInclude('author'));
        $this->assertFalse($builder->hasInclude('owner'));
    }

    public function test_sorts_accept_array_syntax(): void
    {
        $query = $this->builderFor('?sorts[]=-created_at&sorts[]=id')
            ->allowSorts(['created_at', 'id'])
            ->build();

        $orders = $this->orderMap($query);
        $this->assertSame('desc', $orders['created_at'] ?? null);
        $this->assertSame('asc', $orders['id'] ?? null);
    }

    public function test_include_counts_accept_array_syntax(): void
    {
        $query = $this->builderFor('?include_counts[]=peers')
            ->allowIncludeCounts(['peers'])
            ->build();

        $this->assertStringContainsString('peers_count', $query->toSql());
    }

    public function test_include_fields_accept_array_syntax(): void
    {
        $builder = $this->builderFor('?include_fields[torrent][]=seeders')
            ->allowIncludeFields(['seeders', 'leechers']);

        $this->assertTrue($builder->hasIncludeField('seeders'));
        $this->assertFalse($builder->hasIncludeField('leechers'));
    }

    public function test_nested_array_param_does_not_crash(): void
    {
        $query = $this->builderFor('?includes[deep][]=author&sorts[x][y]=id')
            ->allowIncludes(['author'])
            ->allowSorts(['id'])
            ->build();

        $this->assertSame([], $query->getEagerLoads());
        $this->assertSame([], $this->orderMap($query));
    }
}
