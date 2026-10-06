<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reportofferid' => 'nullable|integer',
            'user' => 'nullable|integer',
            'commentid' => 'nullable|integer',
            'torrent' => 'nullable|integer',
            'forumpost' => 'nullable|integer',
            'takeuser' => 'nullable|integer',
            'takecommentid' => 'nullable|integer',
            'taketorrent' => 'nullable|integer',
            'takeforumpost' => 'nullable|integer',
            'takereportofferid' => 'nullable|integer',
            'reason' => 'nullable|string',
        ];
    }
}
