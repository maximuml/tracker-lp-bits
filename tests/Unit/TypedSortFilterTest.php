<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AgentAllowRepository;
use App\Repositories\BaseRepository;
use App\Repositories\MedalRepository;
use App\Repositories\MessageRepository;
use App\Repositories\NewsRepository;
use App\Repositories\RewardRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\UserRepository;
use App\Support\Query\SortDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Wave 5 Step 25: typed sort/filter — whitelist enforcement.
 *
 * Verifies that BaseRepository::getSortFieldAndType() only accepts
 * columns defined in each repository's allowedSortColumns() whitelist,
 * preventing SQL injection via the sort_field request parameter.
 */
final class TypedSortFilterTest extends TestCase
{
    /**
     * SortDirection enum: fromInput defaults to 'desc' for invalid input.
     */
    public function test_sort_direction_defaults_to_desc(): void
    {
        $this->assertSame('desc', SortDirection::fromInput(null)->value);
        $this->assertSame('desc', SortDirection::fromInput('invalid')->value);
        $this->assertSame('desc', SortDirection::fromInput('desc')->value);
    }

    /**
     * SortDirection enum: fromInput returns 'asc' for 'asc' and 'ascending'.
     */
    public function test_sort_direction_asc(): void
    {
        $this->assertSame('asc', SortDirection::fromInput('asc')->value);
        $this->assertSame('asc', SortDirection::fromInput('ascending')->value);
        $this->assertSame('asc', SortDirection::fromInput('ASC')->value);
    }

    /**
     * BaseRepository::getSortFieldAndType() defaults to 'id' when
     * sort_field is not in the whitelist.
     */
    public function test_base_repository_defaults_to_id_for_unknown_sort(): void
    {
        $repo = $this->makeTestRepo(['id', 'name']);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'name']);
        $this->assertSame('name', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'id']);
        $this->assertSame('id', $field);

        // Malicious input — must fall back to 'id'
        [$field] = $repo->getSortFieldAndType(['sort_field' => 'id; DROP TABLE users; --']);
        $this->assertSame('id', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'password']);
        $this->assertSame('id', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => '']);
        $this->assertSame('id', $field);

        [$field] = $repo->getSortFieldAndType([]);
        $this->assertSame('id', $field);
    }

    /**
     * BaseRepository::getSortFieldAndType() validates sort_type.
     */
    public function test_base_repository_validates_sort_type(): void
    {
        $repo = $this->makeTestRepo(['id']);

        [, $type] = $repo->getSortFieldAndType(['sort_type' => 'asc']);
        $this->assertSame('asc', $type);

        [, $type] = $repo->getSortFieldAndType(['sort_type' => 'desc']);
        $this->assertSame('desc', $type);

        [, $type] = $repo->getSortFieldAndType(['sort_type' => 'malicious']);
        $this->assertSame('desc', $type);
    }

    /**
     * RewardRepository has a sort whitelist.
     */
    public function test_reward_repository_has_sort_whitelist(): void
    {
        $repo = $this->wrapRepo(app(RewardRepository::class));
        [$field] = $repo->getSortFieldAndType(['sort_field' => 'value']);
        $this->assertSame('value', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'password']);
        $this->assertSame('id', $field);
    }

    /**
     * MedalRepository has a sort whitelist.
     */
    public function test_medal_repository_has_sort_whitelist(): void
    {
        $repo = $this->wrapRepo(app(MedalRepository::class));
        [$field] = $repo->getSortFieldAndType(['sort_field' => 'name']);
        $this->assertSame('name', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'evil_column']);
        $this->assertSame('id', $field);
    }

    /**
     * MessageRepository has a sort whitelist.
     */
    public function test_message_repository_has_sort_whitelist(): void
    {
        $repo = $this->wrapRepo(app(MessageRepository::class));
        [$field] = $repo->getSortFieldAndType(['sort_field' => 'added']);
        $this->assertSame('added', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'secret']);
        $this->assertSame('id', $field);
    }

    /**
     * SearchBoxRepository has a sort whitelist.
     */
    public function test_searchbox_repository_has_sort_whitelist(): void
    {
        $repo = $this->wrapRepo(app(SearchBoxRepository::class));
        [$field] = $repo->getSortFieldAndType(['sort_field' => 'name']);
        $this->assertSame('name', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'evil']);
        $this->assertSame('id', $field);
    }

    /**
     * UserRepository has a sort whitelist.
     */
    public function test_user_repository_has_sort_whitelist(): void
    {
        $repo = $this->wrapRepo(app(UserRepository::class));
        [$field] = $repo->getSortFieldAndType(['sort_field' => 'username']);
        $this->assertSame('username', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'passkey']);
        $this->assertSame('id', $field);
    }

    /**
     * NewsRepository has a sort whitelist.
     */
    public function test_news_repository_has_sort_whitelist(): void
    {
        $repo = $this->wrapRepo(app(NewsRepository::class));
        [$field] = $repo->getSortFieldAndType(['sort_field' => 'added']);
        $this->assertSame('added', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'evil']);
        $this->assertSame('id', $field);
    }

    /**
     * AgentAllowRepository has a sort whitelist.
     */
    public function test_agent_allow_repository_has_sort_whitelist(): void
    {
        $repo = $this->wrapRepo(app(AgentAllowRepository::class));
        [$field] = $repo->getSortFieldAndType(['sort_field' => 'family']);
        $this->assertSame('family', $field);

        [$field] = $repo->getSortFieldAndType(['sort_field' => 'evil']);
        $this->assertSame('id', $field);
    }

    /**
     * SQL injection vectors in sort_field are blocked.
     *
     * @dataProvider sqlInjectionVectors
     */
    #[DataProvider('sqlInjectionVectors')]
    public function test_sql_injection_vectors_blocked(string $malicious): void
    {
        $repo = $this->makeTestRepo(['id', 'name']);

        [$field] = $repo->getSortFieldAndType(['sort_field' => $malicious]);
        $this->assertSame('id', $field, "SQL injection vector must be blocked: $malicious");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sqlInjectionVectors(): array
    {
        return [
            'semicolon injection' => ['id; DROP TABLE users; --'],
            'union injection' => ['id UNION SELECT 1--'],
            'subquery injection' => ['(SELECT 1)'],
            'comment injection' => ['id--'],
            'sleep injection' => ['SLEEP(5)'],
            'benchmark injection' => ['BENCHMARK(1000000, MD5(1))'],
            'stacked query' => ['id; INSERT INTO users VALUES(1)--'],
            'hex injection' => ['0x41414141'],
            'char injection' => ['CHAR(65,66,67)'],
            'concat injection' => ['CONCAT(1,2)'],
            'load_file injection' => ["LOAD_FILE('/etc/passwd')"],
            'into outfile' => ["id INTO OUTFILE '/tmp/shell.php'"],
        ];
    }

    /**
     * Create a test repository with a custom sort whitelist.
     *
     * @param  list<string>  $allowed
     */
    private function makeTestRepo(array $allowed): TestSortRepository
    {
        return new TestSortRepository($allowed);
    }

    /**
     * Wrap a real repository to expose the protected method for testing.
     */
    private function wrapRepo(BaseRepository $repo): TestSortRepository
    {
        $reflection = new \ReflectionMethod($repo, 'allowedSortColumns');
        $reflection->setAccessible(true);
        /** @var list<string> */
        $allowed = $reflection->invoke($repo);

        return new TestSortRepository($allowed);
    }
}

/**
 * Test helper that exposes getSortFieldAndType() as a public method.
 */
final class TestSortRepository extends BaseRepository
{
    /** @var list<string> */
    private array $allowed;

    /** @param  list<string>  $allowed */
    public function __construct(array $allowed)
    {
        $this->allowed = $allowed;
    }

    /** @return list<string> */
    protected function allowedSortColumns(): array
    {
        return $this->allowed;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return array{0: string, 1: string}
     */
    public function getSortFieldAndType(array $params): array
    {
        return parent::getSortFieldAndType($params);
    }
}
