<?php

namespace NextDeveloper\AI\Http\Requests\LlmApiKeys;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmApiKeysCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'name' => 'required|string',
        'expires_at' => 'nullable|date|after:now',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
