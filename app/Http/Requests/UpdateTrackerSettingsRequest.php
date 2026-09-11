<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-05: Validation for legacy POST /usercp with action=tracker, type=save.
 */
class UpdateTrackerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:tracker',
            'type' => 'required|string|in:save',
            'pmnotif' => 'sometimes|in:yes',
            'emailnotif' => 'sometimes|in:yes',
            'incldead' => 'sometimes|integer',
            'spstate' => 'sometimes|nullable|string|max:50',
            'inclbookmarked' => 'sometimes|nullable|string|max:50',
            'stylesheet' => 'sometimes|integer|min:0',
            'sitelanguage' => 'sometimes|integer|min:0',
            'torrentsperpage' => 'sometimes|integer|min:0|max:100',
            'timetype' => 'sometimes|in:timeadded,timealive,0,1',
            'appendsticky' => 'sometimes|in:yes',
            'appendnew' => 'sometimes|in:yes',
            'appendpromotion' => 'sometimes|in:highlight,word,icon,off,0,1,2,3',
            'appendpicked' => 'sometimes|in:yes',
            'dlicon' => 'sometimes|in:yes',
            'bmicon' => 'sometimes|in:yes',
            'showcomnum' => 'sometimes|in:yes',
            'showdescription' => 'sometimes|in:yes',
            'smalldescr' => 'sometimes|in:yes',
            'showcomment' => 'sometimes|in:yes',
            'pmnum' => 'sometimes|integer|min:1|max:100',
            'sbnum' => 'sometimes|integer|min:10|max:500',
            'sbrefresh' => 'sometimes|integer|min:10|max:3600',
            'tooltip' => 'sometimes|in:minorimdb,medianimdb,off,0,1,2',
            'showlastcom' => 'sometimes|in:yes',
            'fontsize' => 'sometimes|in:small,medium,large,0,1,2',
        ];
    }
}
