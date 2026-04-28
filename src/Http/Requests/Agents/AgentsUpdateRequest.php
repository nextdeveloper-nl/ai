<?php

namespace NextDeveloper\AI\Http\Requests\Agents;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class AgentsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
        'slug' => 'nullable|string',
        'ref_name' => 'nullable|string',
        'description' => 'nullable|string',
        'system_prompt' => 'nullable|string',
        'params' => 'nullable',
        'response_format' => 'string',
        'response_schema' => 'nullable',
        'temperature' => 'numeric',
        'max_tokens' => 'nullable|integer',
        'metadata' => 'nullable',
        'is_active' => 'boolean',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}