<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

final class ToastFeedRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'last_pm_id' => 'nullable|integer|min:0',
            'last_shout_id' => 'nullable|integer|min:0',
            'last_comment_id' => 'nullable|integer|min:0',
            'last_reply_id' => 'nullable|integer|min:0',
            'last_staff_id' => 'nullable|integer|min:0',
            'init' => 'nullable|boolean',
        ];
    }
}
