<?php

namespace NextDeveloper\AI\Http\Requests\LlmProviders;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmProvidersUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'name' => 'nullable|string',
        'slug' => 'nullable|string',
        'driver' => 'nullable|string|in:openai_compatible,anthropic',
        'base_url' => 'nullable|string',
        'is_active' => 'boolean',
        'config' => 'nullable|array',
        'api_key' => 'nullable|string',
        'is_platform' => 'boolean',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
