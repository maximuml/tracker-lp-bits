<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\HitAndRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SettingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<int|string, mixed> */
    public function rules(): array
    {
        $data = $this->all();
        $prefix = Arr::first(array_keys($data));

        $allRules = [
            'hr' => [
                'ban_user_when_counts_reach' => 'required|integer|min:1',
                'ignore_when_ratio_reach' => 'required|numeric',
                'inspect_time' => 'required|integer|min:1',
                'seed_time_minimum' => 'required|integer|lt:hr.inspect_time',
                'mode' => ['required', Rule::in(array_keys(HitAndRun::$modes))],
            ],
        ];

        $result = [];
        foreach ($allRules as $rulePrefix => $rules) {
            if ($rulePrefix != $prefix) {
                continue;
            }
            foreach ($rules as $key => $value) {
                $result["$prefix.$key"] = $value;
            }
        }

        return $result;
    }
}
