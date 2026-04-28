<?php

namespace NextDeveloper\AI\Http\Requests\Runs;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class RunsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'ai_agent_id' => 'required|exists:ai_agents,uuid|uuid',
        'model' => 'required|string',
        'input' => 'nullable',
        'output' => 'nullable|string',
        'parsed_output' => 'nullable',
        'status' => 'string',
        'status_code' => 'nullable|integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost' => '',
        'metadata' => 'nullable',
        'duration_ms' => 'integer',
        'error_message' => 'nullable|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}