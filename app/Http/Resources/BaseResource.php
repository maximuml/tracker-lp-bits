<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    abstract protected function getResourceName(): string;

    /** @param  mixed  $field */
    protected function whenIncludeField($field): bool
    {
        return $this->whenInclude($field, 'include_fields');
    }

    /**
     * @param  mixed  $field
     * @param  mixed  $prefix
     */
    private function whenInclude($field, $prefix): bool
    {
        $fields = request()->input("$prefix.".$this->getResourceName());
        if (! $fields) {
            return false;
        }
        $fieldsArr = explode(',', $fields);

        return in_array($field, $fieldsArr);
    }
}
