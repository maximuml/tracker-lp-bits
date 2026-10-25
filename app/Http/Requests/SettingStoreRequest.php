<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\HitAndRun;
use App\Support\SettingsSchemaValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
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

        $allRules = [
            'hr' => [
                'ban_user_when_counts_reach' => 'required|integer|min:1',
                'ignore_when_ratio_reach' => 'required|numeric',
                'inspect_time' => 'required|integer|min:1',
                'seed_time_minimum' => 'required|integer|lt:hr.inspect_time',
                'mode' => ['required', Rule::in(array_keys(HitAndRun::$modes))],
            ],
        ];

        // Every top-level key must be an array of name => value pairs so all
        // schema-registered prefixes reach the repository — field-level
        // validation for them is delegated to the JSON schemas in saveBatch.
        $result = [];
        foreach (array_keys($data) as $key) {
            $result[$key] = 'array';
        }
        foreach ($allRules as $rulePrefix => $rules) {
            if (! array_key_exists($rulePrefix, $data)) {
                continue;
            }
            foreach ($rules as $key => $value) {
                $result["$rulePrefix.$key"] = $value;
            }
        }

        return $result;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $schemas = app(SettingsSchemaValidator::class)->schemas();
            foreach (array_keys($this->all()) as $prefix) {
                if (! is_string($prefix) || ! isset($schemas[$prefix])) {
                    $validator->errors()->add((string) $prefix, "Unsupported settings prefix: $prefix");
                }
            }
        });
    }
}
