<?php

namespace NextDeveloper\AI\Http\Requests\Conversations;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ConversationsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'ai_session_id' => 'nullable|exists:ai_sessions,uuid|uuid',
        'role' => 'required|string',
        'message' => 'required|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}