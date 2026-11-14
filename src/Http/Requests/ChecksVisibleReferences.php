<?php

namespace NextDeveloper\AI\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * "exists:table,uuid" looks at the whole table, so a caller could point a record at another account's session or
 * agent by guessing its uuid. This asks the model instead, which applies the authorization scope of the caller:
 * what the caller cannot see does not exist for them.
 */
trait ChecksVisibleReferences
{
    /**
     * @param class-string $model
     */
    protected function requireVisible(Validator $validator, string $field, string $model): void
    {
        $validator->after(function (Validator $validator) use ($field, $model): void {
            $uuid = $this->input($field);

            if (!$uuid || $validator->errors()->has($field)) {
                return;
            }

            if (!$model::where('uuid', $uuid)->exists()) {
                $validator->errors()->add($field, "The selected {$field} is invalid.");
            }
        });
    }
}
