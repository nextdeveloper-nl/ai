<?php

namespace NextDeveloper\AI\Http\Requests\LlmAliases;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmAliasesUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'alias' => 'nullable|string',
        'target_provider' => 'nullable|string',
        'target_model' => 'nullable|string',
        'is_active' => 'boolean',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
