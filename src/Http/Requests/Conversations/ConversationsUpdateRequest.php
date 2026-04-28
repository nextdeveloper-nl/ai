<?php

namespace NextDeveloper\AI\Http\Requests\Conversations;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ConversationsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'ai_session_id' => 'nullable|exists:ai_sessions,uuid|uuid',
        'role' => 'nullable|string',
        'message' => 'nullable|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}