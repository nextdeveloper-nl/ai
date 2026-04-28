<?php

namespace NextDeveloper\AI\Http\Requests\Sessions;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class SessionsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'nullable|string',
        'description' => 'nullable|string',
        'ai_engine_name' => 'nullable|string',
        'thread' => 'nullable|string',
        'run' => 'nullable|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}