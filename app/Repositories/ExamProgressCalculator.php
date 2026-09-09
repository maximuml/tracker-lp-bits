<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ExamIndex;
use App\Models\Exam;
use App\Support\Format;
use App\Support\Locale;

/**
 * Pure progress-formatting calculator extracted from ExamProgressRepository.
 *
 * Stateless: takes an Exam model and a progress array, returns a formatted
 * array. Performs no database writes.
 */
final class ExamProgressCalculator
{
    /**
     * @param  array<int|string, mixed>  $progress
     * @param  mixed  $locale
     * @return array<int|string, mixed>
     */
    public function getProgressFormatted(Exam $exam, array $progress, $locale = null): array
    {
        $result = [];
        foreach ($exam->indexes as $key => $index) {
            if (! isset($index['checked']) || ! $index['checked']) {
                continue;
            }
            if (! isset($progress[$index['index']])) {
                continue;
            }
            $currentValue = (float) ($progress[$index['index']] ?? 0);
            $requireValue = $index['require_value'];
            $unit = Exam::$indexes[$index['index']]['unit'] ?? '';
            switch ($index['index']) {
                case ExamIndex::UPLOADED->value:
                case ExamIndex::DOWNLOADED->value:
                    $currentValueFormatted = Format::size($currentValue);
                    $requireValueAtomic = $requireValue * 1024 * 1024 * 1024;
                    break;
                case ExamIndex::SEED_TIME_AVERAGE->value:
                    $currentValueFormatted = number_format($currentValue / 3600, 2)." $unit";
                    $requireValueAtomic = $requireValue * 3600;
                    break;
                default:
                    $currentValueFormatted = $currentValue;
                    $requireValueAtomic = $requireValue;
            }
            $index['name'] = Exam::$indexes[$index['index']]['name'] ?? '';
            $index['index_formatted'] = Locale::trans('exam.index_text_'.$index['index'], [], null);
            $index['require_value_formatted'] = "$requireValue $unit";
            $index['current_value'] = $currentValue;
            $index['current_value_formatted'] = $currentValueFormatted;
            $index['passed'] = $currentValue >= $requireValueAtomic;
            $index['index_result'] = $index['passed'] ? Locale::trans($exam->getPassResultTransKey('pass'), [], null) : Locale::trans($exam->getPassResultTransKey('not_pass'), [], null);
            $result[] = $index;
        }

        return $result;
    }
}
