<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ExamIndex;
use App\Models\Exam;
use App\Repositories\ExamProgressCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for the pure ExamProgressCalculator::getProgressFormatted() method.
 *
 * Covers formatting and pass/not-pass logic across all exam index types,
 * plus skipping of unchecked indexes and missing progress entries.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ExamProgressCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private ExamProgressCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('exam_progress')->delete();
        DB::table('exam_users')->delete();
        DB::table('exams')->delete();
        $this->calculator = new ExamProgressCalculator;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        Model::reguard();
        parent::tearDown();
    }

    public function test_uploaded_index_passes_when_meeting_requirement(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => 1, 'name' => 'Uploaded'],
            ],
        ]);

        // 1 GiB exactly meets require_value of 1 GiB.
        $result = $this->calculator->getProgressFormatted($exam, [ExamIndex::UPLOADED->value => 1073741824]);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['passed']);
        $this->assertSame(ExamIndex::UPLOADED->value, $result[0]['index']);
        $this->assertStringContainsString('GB', $result[0]['require_value_formatted']);
    }

    public function test_uploaded_index_passes_when_exceeding_requirement(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => 1, 'name' => 'Uploaded'],
            ],
        ]);

        $result = $this->calculator->getProgressFormatted($exam, [ExamIndex::UPLOADED->value => 2147483648]);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['passed']);
    }

    public function test_downloaded_index_not_passed_when_below_requirement(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::DOWNLOADED->value, 'checked' => true, 'require_value' => 5, 'name' => 'Downloaded'],
            ],
        ]);

        // 1 GiB is below the 5 GiB requirement.
        $result = $this->calculator->getProgressFormatted($exam, [ExamIndex::DOWNLOADED->value => 1073741824]);

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['passed']);
        $this->assertStringContainsString('GB', $result[0]['require_value_formatted']);
    }

    public function test_seed_time_average_index_formats_hours_and_passes(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::SEED_TIME_AVERAGE->value, 'checked' => true, 'require_value' => 10, 'name' => 'Seed time average'],
            ],
        ]);

        // require_value 10 hours = 36000 seconds; provide 72000 seconds (20 hours).
        $result = $this->calculator->getProgressFormatted($exam, [ExamIndex::SEED_TIME_AVERAGE->value => 72000]);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['passed']);
        $this->assertStringContainsString('Hour', $result[0]['require_value_formatted']);
        $this->assertSame('20.00 Hour', $result[0]['current_value_formatted']);
    }

    public function test_seed_bonus_index_passes(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::SEED_BONUS->value, 'checked' => true, 'require_value' => 100, 'name' => 'Bonus'],
            ],
        ]);

        $result = $this->calculator->getProgressFormatted($exam, [ExamIndex::SEED_BONUS->value => 200]);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['passed']);
        $this->assertSame(200.0, $result[0]['current_value']);
        $this->assertSame(200.0, $result[0]['current_value_formatted']);
    }

    public function test_unchecked_indexes_are_skipped(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::UPLOADED->value, 'checked' => false, 'require_value' => 100, 'name' => 'Uploaded'],
                ['index' => ExamIndex::SEED_BONUS->value, 'checked' => true, 'require_value' => 100, 'name' => 'Bonus'],
            ],
        ]);

        $result = $this->calculator->getProgressFormatted($exam, [
            ExamIndex::UPLOADED->value => 999999,
            ExamIndex::SEED_BONUS->value => 200,
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(ExamIndex::SEED_BONUS->value, $result[0]['index']);
    }

    public function test_progress_missing_some_indexes_are_skipped(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => 1, 'name' => 'Uploaded'],
                ['index' => ExamIndex::SEED_BONUS->value, 'checked' => true, 'require_value' => 100, 'name' => 'Bonus'],
            ],
        ]);

        // Only provide SEED_BONUS progress; UPLOADED should be skipped.
        $result = $this->calculator->getProgressFormatted($exam, [ExamIndex::SEED_BONUS->value => 200]);

        $this->assertCount(1, $result);
        $this->assertSame(ExamIndex::SEED_BONUS->value, $result[0]['index']);
    }

    public function test_returns_empty_array_when_all_indexes_unchecked(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::UPLOADED->value, 'checked' => false, 'require_value' => 100, 'name' => 'Uploaded'],
            ],
        ]);

        $result = $this->calculator->getProgressFormatted($exam, [ExamIndex::UPLOADED->value => 999]);

        $this->assertSame([], $result);
    }

    public function test_multiple_indexes_formatted_together(): void
    {
        $exam = Exam::factory()->create([
            'indexes' => [
                ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => 1, 'name' => 'Uploaded'],
                ['index' => ExamIndex::DOWNLOADED->value, 'checked' => true, 'require_value' => 1, 'name' => 'Downloaded'],
                ['index' => ExamIndex::SEED_BONUS->value, 'checked' => true, 'require_value' => 100, 'name' => 'Bonus'],
            ],
        ]);

        $result = $this->calculator->getProgressFormatted($exam, [
            ExamIndex::UPLOADED->value => 1073741824,
            ExamIndex::DOWNLOADED->value => 536870912,
            ExamIndex::SEED_BONUS->value => 50,
        ]);

        $this->assertCount(3, $result);
        $this->assertTrue($result[0]['passed']);
        $this->assertFalse($result[1]['passed']);
        $this->assertFalse($result[2]['passed']);
    }
}
