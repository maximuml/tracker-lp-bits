<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\MeiliSearchFilterService;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression for audit L12: malformed `added_begin`/`added_end` params
 * used to produce a broken Meili filter (`added >= `) or a Carbon
 * exception — both surfaced as a Meili error and a silent SQL fallback.
 * Invalid dates are now skipped with a warning instead.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class MeiliSearchFilterServiceTest extends TestCase
{
    private function service(): MeiliSearchFilterService
    {
        return app(MeiliSearchFilterService::class);
    }

    private function user(): User
    {
        $user = new User;
        $user->id = 1;
        $user->notifs = '';

        return $user;
    }

    /** @param  array<int|string, mixed>  $params */
    private function addedFilters(array $params): array
    {
        return array_values(array_filter(
            $this->service()->getFilters($params, $this->user()),
            fn ($f) => str_starts_with((string) $f, 'added ')
        ));
    }

    public function test_invalid_added_begin_is_skipped(): void
    {
        $this->assertSame([], $this->addedFilters(['added_begin' => 'not-a-date']));
    }

    public function test_invalid_added_end_is_skipped(): void
    {
        $this->assertSame([], $this->addedFilters(['added_end' => 'totally invalid']));
    }

    public function test_valid_added_begin_produces_timestamp_filter(): void
    {
        $filters = $this->addedFilters(['added_begin' => '2026-01-15']);
        $this->assertCount(1, $filters);
        $this->assertSame('added >= '.strtotime('2026-01-15'), $filters[0]);
    }

    public function test_valid_added_end_uses_end_of_day(): void
    {
        $filters = $this->addedFilters(['added_end' => '2026-01-15']);
        $this->assertCount(1, $filters);
        $this->assertSame('added <= '.strtotime('2026-01-15 23:59:59'), $filters[0]);
    }

    public function test_invalid_and_valid_dates_mix_correctly(): void
    {
        $filters = $this->addedFilters([
            'added_begin' => 'garbage',
            'added_end' => '2026-01-15',
        ]);
        $this->assertSame(['added <= '.strtotime('2026-01-15 23:59:59')], $filters);
    }
}
