<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Exam;

interface ExamProgressCalculatorInterface
{
    /**
     * @param  array<int|string, mixed>  $progress
     * @param  mixed  $locale
     * @return array<int|string, mixed>
     */
    public function getProgressFormatted(Exam $exam, array $progress, $locale = null): array;
}
