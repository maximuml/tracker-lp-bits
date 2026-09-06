<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ExamUserIndexRequest extends IndexRequest
{
    /** @return array<int|string, mixed> */
    protected function filterRules(): array
    {
        return [
            'uid' => 'nullable|integer',
            'exam_id' => 'nullable|integer',
            'status' => 'nullable|string',
        ];
    }
}
