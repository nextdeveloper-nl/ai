<?php

namespace NextDeveloper\AI\Http\Requests\Conversations;

use NextDeveloper\AI\Database\Models\Sessions;
use NextDeveloper\AI\Http\Requests\ChecksVisibleReferences;
use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ConversationsUpdateRequest extends AbstractFormRequest
{
    use ChecksVisibleReferences;

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'ai_session_id' => 'nullable|exists:ai_sessions,uuid|uuid',
        'role' => 'nullable|string',
        'message' => 'nullable|string',
        'metadata' => 'nullable',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    public function withValidator($validator): void
    {
        $this->requireVisible($validator, 'ai_session_id', Sessions::class);
    }
}