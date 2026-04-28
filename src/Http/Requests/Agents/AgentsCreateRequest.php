<?php

namespace NextDeveloper\AI\Http\Requests\Agents;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class AgentsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
            'slug' => 'required|string',
            'ref_name' => 'nullable|string',
            'description' => 'nullable|string',
            'system_prompt' => 'required|string',
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
