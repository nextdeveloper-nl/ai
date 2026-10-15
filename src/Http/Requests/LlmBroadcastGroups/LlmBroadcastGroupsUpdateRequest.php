<?php

namespace NextDeveloper\AI\Http\Requests\LlmBroadcastGroups;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmBroadcastGroupsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'name' => 'nullable|string',
        'mode' => 'nullable|string|in:broadcast,first',
        'timeout_ms' => 'nullable|integer|min:1000',
        'targets' => 'nullable|array|min:1',
        'is_active' => 'boolean',
        // Each target: {alias} or {provider, model}; returns 422 instead of a 500.
        'targets.*.alias' => 'nullable|string|required_without_all:targets.*.provider,targets.*.model',
        'targets.*.provider' => 'nullable|string|required_without:targets.*.alias',
        'targets.*.model' => 'nullable|string|required_with:targets.*.provider',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
