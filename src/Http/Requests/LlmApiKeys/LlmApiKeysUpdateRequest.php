<?php

namespace NextDeveloper\AI\Http\Requests\LlmApiKeys;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmApiKeysUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'name' => 'nullable|string',
        'expires_at' => 'nullable|date',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
