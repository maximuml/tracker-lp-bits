<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for AttendanceRepository.
 *
 * Covers attend(), getAttendance(), getContinuousPoints(),
 * getContinuousDays(), cleanup(), migrateAttendance(), and
 * buildViewData().
 */
final class AttendanceRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AttendanceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('attendance_logs')->delete();
        DB::table('attendance')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new AttendanceRepository;
    }

    public function test_get_attendance_returns_null_when_not_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getAttendance($user->id);

        $this->assertNull($result);
    }

    public function test_get_attendance_returns_latest_record(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $firstId = $this->insertAttendance($user->id, now()->subDays(2));
        $secondId = $this->insertAttendance($user->id, now()->subDay());

        $result = $this->repository->getAttendance($user->id);

        $this->assertInstanceOf(Attendance::class, $result);
        $this->assertSame($secondId, $result->id);
    }

    public function test_get_attendance_filters_by_date(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $oldId = $this->insertAttendance($user->id, now()->subDays(5));

        $result = $this->repository->getAttendance($user->id, now()->subDays(5)->format('Y-m-d'));

        $this->assertInstanceOf(Attendance::class, $result);
        $this->assertSame($oldId, $result->id);
    }

    public function test_attend_creates_first_attendance(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $attendance = $this->repository->attend($user->id);

        $this->assertInstanceOf(Attendance::class, $attendance);
        $this->assertSame(1, (int) $attendance->days);
        $this->assertSame(1, (int) $attendance->total_days);
        $this->assertSame(1, (int) $attendance->getAttribute('is_updated'));
    }

    public function test_attend_does_not_update_when_already_attended_today(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->insertAttendance($user->id, now());

        $attendance = $this->repository->attend($user->id);

        $this->assertSame(0, (int) $attendance->getAttribute('is_updated'));
    }

    public function test_attend_increments_seedbonus(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 100.0]);

        $this->repository->attend($user->id);

        $user->refresh();
        $this->assertGreaterThan(100.0, (float) $user->seedbonus);
    }

    public function test_attend_creates_attendance_log(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->repository->attend($user->id);

        $logCount = AttendanceLog::query()->where('uid', $user->id)->count();
        $this->assertSame(1, $logCount);
    }

    public function test_get_continuous_points_returns_initial_for_day_one(): void
    {
        $points = $this->repository->getContinuousPoints(1);

        $this->assertIsNumeric($points);
        $this->assertGreaterThan(0, $points);
    }

    public function test_get_continuous_points_increases_with_days(): void
    {
        $day1 = $this->repository->getContinuousPoints(1);
        $day5 = $this->repository->getContinuousPoints(5);

        $this->assertGreaterThanOrEqual($day1, $day5);
    }

    public function test_get_continuous_days_returns_zero_when_no_logs(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $attendance = Attendance::query()->create([
            'uid' => $user->id,
            'added' => now(),
            'points' => 10,
            'days' => 1,
            'total_days' => 1,
        ]);

        $days = $this->repository->getContinuousDays($attendance, now());

        $this->assertSame(0, $days);
    }

    public function test_get_continuous_days_counts_consecutive_days(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $attendance = Attendance::query()->create([
            'uid' => $user->id,
            'added' => now(),
            'points' => 10,
            'days' => 3,
            'total_days' => 3,
        ]);
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $dayBefore = now()->subDays(2)->format('Y-m-d');
        DB::table('attendance_logs')->insert([
            ['uid' => $user->id, 'points' => 10, 'date' => $today, 'created_at' => now(), 'updated_at' => now()],
            ['uid' => $user->id, 'points' => 10, 'date' => $yesterday, 'created_at' => now(), 'updated_at' => now()],
            ['uid' => $user->id, 'points' => 10, 'date' => $dayBefore, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $days = $this->repository->getContinuousDays($attendance, now());

        $this->assertSame(3, $days);
    }

    public function test_get_continuous_days_stops_at_gap(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $attendance = Attendance::query()->create([
            'uid' => $user->id,
            'added' => now(),
            'points' => 10,
            'days' => 1,
            'total_days' => 1,
        ]);
        $today = now()->format('Y-m-d');
        $dayBefore = now()->subDays(2)->format('Y-m-d');
        DB::table('attendance_logs')->insert([
            ['uid' => $user->id, 'points' => 10, 'date' => $today, 'created_at' => now(), 'updated_at' => now()],
            ['uid' => $user->id, 'points' => 10, 'date' => $dayBefore, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $days = $this->repository->getContinuousDays($attendance, now());

        $this->assertSame(1, $days);
    }

    public function test_cleanup_returns_zero_when_no_duplicates(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->insertAttendance($user->id, now());

        $result = $this->repository->cleanup();

        $this->assertSame(0, $result);
    }

    public function test_cleanup_removes_duplicates_keeping_latest(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $oldId = $this->insertAttendance($user->id, now()->subDays(2));
        $newId = $this->insertAttendance($user->id, now()->subDay());

        $result = $this->repository->cleanup();

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertSame(0, Attendance::query()->where('id', $oldId)->count());
        $this->assertSame(1, Attendance::query()->where('id', $newId)->count());
    }

    public function test_migrate_attendance_returns_zero_when_no_data(): void
    {
        $result = $this->repository->migrateAttendance();

        $this->assertSame(0, $result);
    }

    public function test_migrate_attendance_updates_total_days(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->insertAttendance($user->id, now()->subDays(2));
        $this->insertAttendance($user->id, now()->subDay());
        $this->insertAttendance($user->id, now());

        $result = $this->repository->migrateAttendance();

        $this->assertSame(1, $result);
    }

    public function test_build_view_data_returns_array_when_no_attendance(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $data = $this->repository->buildViewData(null, $user->id);

        $this->assertFalse($data['hasAttendedToday']);
        $this->assertSame(0, $data['todayCounts']);
        $this->assertSame(0, $data['myRanking']);
        $this->assertIsArray($data['events']);
    }

    public function test_build_view_data_returns_array_when_attended_today(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $attendance = Attendance::query()->create([
            'uid' => $user->id,
            'added' => now(),
            'points' => 10,
            'days' => 1,
            'total_days' => 1,
        ]);
        DB::table('attendance_logs')->insert([
            'uid' => $user->id,
            'points' => 10,
            'date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $this->repository->buildViewData($attendance, $user->id);

        $this->assertTrue($data['hasAttendedToday']);
        $this->assertSame(1, $data['todayCounts']);
        $this->assertSame(1, $data['myRanking']);
    }

    private function insertAttendance(int $userId, Carbon|string $added): int
    {
        return (int) DB::table('attendance')->insertGetId([
            'uid' => $userId,
            'added' => $added,
            'points' => 10,
            'days' => 1,
            'total_days' => 1,
        ]);
    }
}
