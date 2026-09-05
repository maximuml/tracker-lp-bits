<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\MeiliSearchRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit tests for MeiliSearchRepository.
 *
 * Covers formatValueForMeili(), getRequiredFields(), isEnabled().
 * Methods that require a live MeiliSearch server (search, import,
 * deleteDocuments, getClient) are excluded from unit testing.
 */
final class MeiliSearchRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private MeiliSearchRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MeiliSearchRepository;
    }

    public function test_format_value_for_meili_converts_yes_to_int_for_yesno_field(): void
    {
        $result = $this->repository->formatValueForMeili('visible', 'yes');

        $this->assertSame(1, $result);
    }

    public function test_format_value_for_meili_converts_no_to_int_for_yesno_field(): void
    {
        $result = $this->repository->formatValueForMeili('visible', 'no');

        $this->assertSame(0, $result);
    }

    public function test_format_value_for_meili_converts_bool_true_to_int_for_yesno_field(): void
    {
        $result = $this->repository->formatValueForMeili('anonymous', true);

        $this->assertSame(1, $result);
    }

    public function test_format_value_for_meili_converts_bool_false_to_int_for_yesno_field(): void
    {
        $result = $this->repository->formatValueForMeili('banned', false);

        $this->assertSame(0, $result);
    }

    public function test_format_value_for_meili_converts_int_1_for_yesno_field(): void
    {
        $result = $this->repository->formatValueForMeili('visible', 1);

        $this->assertSame(1, $result);
    }

    public function test_format_value_for_meili_converts_int_0_for_yesno_field(): void
    {
        $result = $this->repository->formatValueForMeili('visible', 0);

        $this->assertSame(0, $result);
    }

    public function test_format_value_for_meili_casts_int_fields_to_int(): void
    {
        $result = $this->repository->formatValueForMeili('category', '5');

        $this->assertSame(5, $result);
    }

    public function test_format_value_for_meili_casts_string_numeric_to_int_for_int_field(): void
    {
        $result = $this->repository->formatValueForMeili('size', '1073741824');

        $this->assertSame(1073741824, $result);
    }

    public function test_format_value_for_meili_converts_timestamp_field_to_unix_timestamp(): void
    {
        $result = $this->repository->formatValueForMeili('added', '2025-01-15 10:30:00');

        $this->assertSame(strtotime('2025-01-15 10:30:00'), $result);
    }

    public function test_format_value_for_meili_returns_string_for_other_fields(): void
    {
        $result = $this->repository->formatValueForMeili('name', 'Test Torrent');

        $this->assertSame('Test Torrent', $result);
    }

    public function test_format_value_for_meili_converts_int_to_string_for_name_field(): void
    {
        $result = $this->repository->formatValueForMeili('name', 12345);

        $this->assertSame('12345', $result);
    }

    public function test_get_required_fields_returns_array_with_required_attributes(): void
    {
        $fields = $this->repository->getRequiredFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);
        $this->assertContains('id', $fields);
        $this->assertContains('category', $fields);
        $this->assertContains('name', $fields);
    }

    public function test_get_required_fields_contains_filterable_and_sortable_and_searchable(): void
    {
        $fields = $this->repository->getRequiredFields();

        // Filterable
        $this->assertContains('size', $fields);
        $this->assertContains('seeders', $fields);
        // Sortable
        $this->assertContains('added', $fields);
        $this->assertContains('comments', $fields);
        // Searchable
        $this->assertContains('name', $fields);
        $this->assertContains('url', $fields);
    }

    public function test_get_required_fields_returns_unique_values(): void
    {
        $fields = $this->repository->getRequiredFields();

        $this->assertSame(count($fields), count(array_unique($fields)));
    }

    public function test_is_enabled_returns_bool(): void
    {
        $result = $this->repository->isEnabled();

        $this->assertIsBool($result);
    }
}
