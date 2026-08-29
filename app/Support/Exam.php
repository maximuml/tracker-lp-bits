<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ExamUserStatus;
use App\Repositories\ExamRepository;

class Exam
{
    /** @return array{exam: \App\Models\Exam|null, html: string} */
    public function getCurrent(int $uid): array
    {
        $examRep = app(ExamRepository::class);
        $userExam = $examRep->getUserExamProgress($uid, ExamUserStatus::NORMAL->value);
        if (empty($userExam)) {
            return ['exam' => null, 'html' => ''];
        }
        /** @var \App\Models\Exam $exam */
        $exam = $userExam->exam;
        $row = [];
        $row[] = sprintf('%s：%s', Locale::trans('exam.name', [], null), $exam->name);
        $row[] = sprintf('%s：%s ~ %s', Locale::trans('exam.time_range', [], null), $userExam->begin, $userExam->end);
        foreach ($userExam->progress_formatted as $key => $index) {
            if (isset($index['checked']) && $index['checked']) {
                $row[] = sprintf(
                    '%s：%s, %s：%s, %s：%s, %s：%s',
                    Locale::trans('exam.index', [], null).($key + 1), Locale::trans('exam.index_text_'.$index['index'], [], null),
                    Locale::trans('exam.require_value', [], null), $index['require_value_formatted'],
                    Locale::trans('exam.current_value', [], null), $index['current_value_formatted'],
                    Locale::trans('exam.result', [], null),
                    $index['passed'] ? Locale::trans($exam->getPassResultTransKey('pass'), [], null) : Locale::trans($exam->getPassResultTransKey('not_pass'), [], null)
                );
            }
        }
        if ($exam->description) {
            $row[] = "\n".$exam->description;
        }
        $html = nl2br(implode("\n", $row));

        return compact('exam', 'html');
    }
}
